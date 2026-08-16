(() => {
  "use strict";

  const config = window.SentifydWordPressProvider || {};

  const normalizeCapabilities = (value) => {
    if (Array.isArray(value)) return value;
    if (typeof value === "string") return value.split(",").map((item) => item.trim()).filter(Boolean);
    return [];
  };

  const capabilities = new Set(normalizeCapabilities(config.capabilities));
  const wpRoot = String(config.wpApiRoot || "/wp-json/");
  const wpRootUrl = new URL(wpRoot, window.location.href);
  const storeRootUrl = new URL(
    `${wpRootUrl.toString().replace(/\/?$/, "/")}wc/store/v1/`,
    window.location.href
  );
  const storeRoot = storeRootUrl.toString();
  const CATALOG_SELECTION_TTL_MS = 5 * 60 * 1000;
  const REQUEST_TIMEOUT_MS = 10000;
  const MAX_CART_QUANTITY = 999;
  const SEARCH_MIN_LIMIT = 1;
  const SEARCH_MAX_LIMIT = 24;
  const ANONYMOUS_CONVERSATION = "anonymous";

  const ACTIONS = {
    searchProducts: "woocommerce.search_products",
    getProduct: "woocommerce.get_product",
  };

  const NONCE_ERROR_CODES = new Set([
    "woocommerce_rest_invalid_nonce",
    "woocommerce_rest_cart_invalid_nonce",
    "woocommerce_rest_cookie_invalid_nonce",
  ]);

  let storeNonce = null;
  let noncePromise = null;

  const debug = (...args) => {
    if (config.debug) console.debug("[SentifydWPProvider]", ...args);
  };

  const available = (name) => capabilities.has(name);

  const conversationId = (context) => context?.conversationId || null;

  const conversationKey = (context) => conversationId(context) || ANONYMOUS_CONVERSATION;

  const catalogSearchCache = new Map();
  const catalogProductCache = new Map();

  const getCacheEntry = (cache, context) => {
    const key = conversationKey(context);
    const entry = cache.get(key);
    if (!entry) return null;
    if (entry.expiresAt <= Date.now()) {
      cache.delete(key);
      return null;
    }
    return entry;
  };

  const query = (parameters) => new URLSearchParams(
    Object.entries(parameters).filter(([, value]) => value !== undefined && value !== null && value !== "")
  ).toString();

  const buildStoreUrl = (route, parameters = {}) => {
    const url = new URL(route.replace(/^\//, ""), storeRoot);
    const search = query(parameters);
    if (search) url.search = search;
    return url.toString();
  };

  const buildWpUrl = (route, parameters = {}) => {
    const base = new URL(wpRoot, window.location.href);
    const search = query(parameters);
    const cleanRoute = route.replace(/^\/+|\/+$/g, "");

    if (base.searchParams.has("rest_route")) {
      const restRoute = base.searchParams.get("rest_route").replace(/\/?$/, "");
      base.searchParams.set("rest_route", `${restRoute}/${cleanRoute}`);
      if (search) {
        new URLSearchParams(search).forEach((value, key) => base.searchParams.set(key, value));
      }
      return base.toString();
    }

    const root = base.toString().replace(/\/?$/, "/");
    return `${root}${cleanRoute}${search ? `?${search}` : ""}`;
  };

  const isNonceError = (error) => (
    NONCE_ERROR_CODES.has(error?.code)
    || (error?.status === 403 && /nonce/i.test(error?.message || ""))
  );

  const request = async (path, options = {}) => {
    const headers = new Headers(options.headers || {});
    headers.set("Accept", "application/json");

    let body = options.body;
    if (body && typeof body === "object" && !(body instanceof FormData) && !(body instanceof Blob)) {
      body = JSON.stringify(body);
      headers.set("Content-Type", "application/json");
    }

    if (storeNonce && String(path).startsWith(storeRoot)) {
      headers.set("Nonce", storeNonce);
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

    let response;
    try {
      response = await fetch(path, {
        ...options,
        body,
        credentials: "same-origin",
        headers,
        signal: controller.signal,
      });
    } catch (networkError) {
      const error = new Error(networkError?.name === "AbortError"
        ? "WordPress request timed out"
        : "WordPress request failed to complete");
      error.code = networkError?.name === "AbortError" ? "SENTIFYD_WP_TIMEOUT" : "SENTIFYD_WP_NETWORK_ERROR";
      throw error;
    } finally {
      clearTimeout(timeout);
    }

    const nextNonce = response.headers.get("Nonce");
    if (nextNonce) storeNonce = nextNonce;

    const contentType = response.headers.get("Content-Type") || "";
    if (response.ok && !contentType.includes("application/json")) {
      const error = new Error("Unexpected non-JSON response from WordPress");
      error.code = "SENTIFYD_WP_BAD_RESPONSE";
      error.status = response.status;
      throw error;
    }

    const responseBody = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(responseBody.message || `WordPress request failed (${response.status})`);
      error.code = responseBody.code || "PROVIDER_ERROR";
      error.status = response.status;
      throw error;
    }
    return responseBody;
  };

  const ensureStoreNonce = async () => {
    if (storeNonce) return;
    if (!noncePromise) {
      noncePromise = request(buildStoreUrl("cart"))
        .catch((error) => {
          debug("Failed to obtain WooCommerce store nonce", error);
          throw error;
        })
        .finally(() => {
          noncePromise = null;
        });
    }
    await noncePromise;
  };

  const storeRequest = async (path, options = {}, allowNonceRetry = true) => {
    try {
      return await request(path, options);
    } catch (error) {
      if (allowNonceRetry && isNonceError(error)) {
        debug("Store nonce rejected, refreshing and retrying", error);
        storeNonce = null;
        await ensureStoreNonce();
        return request(path, options);
      }
      throw error;
    }
  };

  const clampSearchLimit = (perPage) => Math.min(
    Math.max(Number(perPage) || 6, SEARCH_MIN_LIMIT),
    SEARCH_MAX_LIMIT
  );

  const searchProducts = async (search, category, perPage) => {
    const limit = clampSearchLimit(perPage);
    const products = await request(buildStoreUrl("products", { search, category, per_page: limit }));
    if (!Array.isArray(products)) throw new Error("Unexpected product search response");
    if (products.length || typeof search !== "string") return products;

    const terms = [...new Set(search.trim().split(/\s+/).filter((term) => term.length >= 2))];
    if (!terms.length) return [];

    const settled = await Promise.allSettled(terms.map((term) => (
      request(buildStoreUrl("products", { search: term, category, per_page: limit }))
    )));

    const seen = new Set();
    const merged = [];
    for (const outcome of settled) {
      if (outcome.status !== "fulfilled" || !Array.isArray(outcome.value)) continue;
      for (const product of outcome.value) {
        if (seen.has(product.id)) continue;
        seen.add(product.id);
        merged.push(product);
      }
    }
    return merged;
  };

  const plainText = (value, limit = 4000) => {
    if (!value) return "";
    const doc = new DOMParser().parseFromString(String(value), "text/html");
    doc.querySelectorAll("script, style, noscript, template, iframe, object, embed").forEach((el) => el.remove());
    return (doc.body?.textContent || "").replace(/\s+/g, " ").trim().slice(0, limit);
  };

  const productSummary = (product) => ({
    id: product.id,
    name: plainText(product.name, 200),
    description: plainText(product.short_description || product.description || "", 500),
    price: product.prices?.price,
    currency: product.prices?.currency_code,
    currencyMinorUnit: product.prices?.currency_minor_unit,
    inStock: product.is_in_stock,
    isPurchasable: product.is_purchasable,
    hasOptions: product.has_options,
    permalink: product.permalink,
  });

  const variationSummary = (variation) => ({
    id: variation.variation_id ?? variation.id,
    attributes: variation.attributes || {},
    price: variation.display_price ?? variation.price,
    inStock: variation.is_in_stock ?? variation.inStock,
    isPurchasable: variation.is_purchasable ?? variation.isPurchasable,
  });

  // Prefer the plugin's REST endpoint for reliable, API-based variation data.
  // Fall back to scraping the product page markup when the endpoint is
  // unavailable (e.g. older plugin version) or returns nothing.
  const fetchVariationsFromEndpoint = async (productId) => {
    try {
      const result = await request(buildWpUrl(`sentifyd/v1/products/${encodeURIComponent(productId)}/variations`));
      return Array.isArray(result) ? result : [];
    } catch (error) {
      debug("Variations endpoint unavailable, falling back to page scraping", error);
      return null; // null signals "endpoint unavailable", [] signals "no variations"
    }
  };

  const scrapeVariationsFromPage = async (product) => {
    let url;
    try {
      url = new URL(product.permalink, window.location.href);
    } catch (_) {
      debug("Invalid product permalink for variation lookup", product.permalink);
      return [];
    }
    if (url.origin !== window.location.origin) {
      debug("Skipping cross-origin variation lookup", url.origin);
      return [];
    }

    let response;
    try {
      response = await fetch(url.toString(), {
        credentials: "same-origin",
        headers: { Accept: "text/html" },
      });
    } catch (networkError) {
      debug("Variation page fetch failed", networkError);
      return [];
    }
    if (!response.ok) {
      debug("Variation page returned", response.status);
      return [];
    }

    const page = new DOMParser().parseFromString(await response.text(), "text/html");
    const form = page.querySelector("form.variations_form[data-product_variations]");
    const serialized = form?.getAttribute("data-product_variations");
    if (!serialized || serialized === "false") {
      debug("No variations data found on product page", url.toString());
      return [];
    }

    try {
      const variations = JSON.parse(serialized);
      return Array.isArray(variations) ? variations : [];
    } catch (parseError) {
      debug("Failed to parse variations data", parseError);
      return [];
    }
  };

  const getProductVariations = async (product) => {
    if (!product?.has_options) return [];

    const fromEndpoint = await fetchVariationsFromEndpoint(product.id);
    if (fromEndpoint !== null && fromEndpoint.length) {
      return fromEndpoint.map(variationSummary);
    }

    if (!product.permalink) return [];
    const scraped = await scrapeVariationsFromPage(product);
    return scraped.map(variationSummary);
  };

  const mostRecentProductLookup = (context) => [...(context?.actionResults || [])]
    .reverse()
    .find((result) => result.actionName === ACTIONS.getProduct);

  const mostRecentProductSearch = (context) => [...(context?.actionResults || [])]
    .reverse()
    .find((result) => result.actionName === ACTIONS.searchProducts);

  const saveCatalogSearch = (products, context) => {
    const key = conversationKey(context);
    catalogSearchCache.set(key, {
      products,
      expiresAt: Date.now() + CATALOG_SELECTION_TTL_MS,
    });
    catalogProductCache.delete(key);
  };

  const getCatalogSearch = (context) => {
    const search = mostRecentProductSearch(context);
    if (search?.ok && Array.isArray(search.result?.products)) return search.result;
    return getCacheEntry(catalogSearchCache, context);
  };

  const saveCatalogProduct = (product, context) => {
    catalogProductCache.set(conversationKey(context), {
      product,
      expiresAt: Date.now() + CATALOG_SELECTION_TTL_MS,
    });
  };

  const getCatalogProduct = (context) => getCacheEntry(catalogProductCache, context)?.product || null;

  const cartSummary = (cart) => ({
    itemCount: cart.items_count,
    total: cart.totals?.total_price,
    currency: cart.totals?.currency_code,
    currencyMinorUnit: cart.totals?.currency_minor_unit,
    items: (cart.items || []).map((item) => ({
      key: item.key,
      productId: item.id,
      name: plainText(item.name, 200),
      quantity: item.quantity,
      total: item.totals?.line_total,
    })),
  });

  // Notify the host WooCommerce theme that the cart changed so cart widgets,
  // mini-carts and counts refresh without a page reload. The Store API does
  // not emit the theme's jQuery events, so we trigger them and ask WooCommerce
  // to refresh its cart fragments. All hooks are defensive: if jQuery or the
  // fragments script are absent (some themes/builders), this is a no-op.
  const notifyCartChanged = (cart, eventName = "added_to_cart") => {
    try {
      const $ = window.jQuery;
      if ($) {
        // Standard WooCommerce events most themes listen to.
        $(document.body).trigger("wc_fragment_refresh");
        $(document.body).trigger(eventName, [null, null, null]);
        $(document.body).trigger("updated_wc_div");
        $(document.body).trigger("wc_cart_updated");
      }

      // Refresh persisted cart fragments so the mini-cart/count reflect the
      // new totals on next paint, even across page navigations.
      if ($ && typeof window.wc_cart_fragments_params !== "undefined") {
        $.post(
          window.wc_cart_fragments_params.wc_ajax_url
            ?.toString()
            .replace("%%endpoint%%", "get_refreshed_fragments") || "/?wc-ajax=get_refreshed_fragments"
        ).done((data) => {
          if (data && data.fragments) {
            $.each(data.fragments, (selector, html) => {
              $(selector).replaceWith(html);
            });
            $(document.body).trigger("wc_fragments_refreshed");
          }
        });
      }

      // Fallback for block-based / non-jQuery themes: dispatch a DOM event and
      // poke the WooCommerce Blocks cart data store if present.
      window.dispatchEvent(new CustomEvent("sentifyd:cart-updated", { detail: { cart } }));
      const blocksStore = window.wp?.data?.dispatch?.("wc/store/cart");
      if (blocksStore && typeof blocksStore.invalidateResolutionForStore === "function") {
        blocksStore.invalidateResolutionForStore();
      } else if (window.wp?.data?.dispatch) {
        const cartStore = window.wp.data.dispatch("wc/store/cart");
        cartStore?.receiveCart?.(cart);
      }
    } catch (syncError) {
      debug("Cart change notification failed", syncError);
    }
  };

  const normalizePostTypeRoute = (postType) => {
    const type = String(postType || "posts").toLowerCase();
    const map = {
      post: "posts",
      posts: "posts",
      page: "pages",
      pages: "pages",
      product: "products",
      products: "products",
    };
    return map[type] || type;
  };

  const normalizeSlug = (value) => {
    try {
      return decodeURIComponent(String(value || "")).replace(/^\/+|\/+$/g, "").toLowerCase();
    } catch (_) {
      return String(value || "").replace(/^\/+|\/+$/g, "").toLowerCase();
    }
  };

  const permalinkSlug = (permalink) => {
    try {
      const segments = new URL(permalink, window.location.href).pathname.split("/").filter(Boolean);
      return normalizeSlug(segments.pop() || "");
    } catch (_) {
      return "";
    }
  };

  const parsePositiveInteger = (value, label, { required = true } = {}) => {
    if (value === null || value === undefined || value === "") {
      if (required) throw new Error(`A valid ${label} is required`);
      return null;
    }
    const numeric = Number(value);
    if (!Number.isInteger(numeric) || numeric <= 0) {
      throw new Error(`${label} must be a positive integer`);
    }
    return numeric;
  };

  const validateQuantity = (quantity, { minimum = 1 } = {}) => {
    const numeric = quantity == null ? 1 : Number(quantity);
    if (!Number.isInteger(numeric) || numeric < minimum) {
      throw new Error(`quantity must be an integer of at least ${minimum}`);
    }
    if (numeric > MAX_CART_QUANTITY) {
      throw new Error(`quantity must be ${MAX_CART_QUANTITY} or less`);
    }
    return numeric;
  };

  const validateCartItemKey = (key) => {
    if (typeof key !== "string" || key.trim().length === 0) {
      throw new Error("cart_item_key is required");
    }
    return key;
  };

  const actionDefinitions = [
    {
      name: "wordpress.search_posts",
      provider: "wordpress",
      description: "Search published WordPress posts and pages. Returns compact public results only.",
      inputSchema: {
        type: "object",
        properties: {
          query: { type: "string", minLength: 1, description: "Search terms." },
          post_type: { type: "string", description: "Optional post type, e.g. 'posts' or 'pages'." },
          per_page: { type: "integer", minimum: 1, maximum: 20, description: "Number of results. Defaults to 5." },
        },
        required: ["query"],
      },
      execute: async ({ query: search, post_type: type, per_page: perPage }) => {
        if (typeof search !== "string" || search.trim().length === 0) {
          throw new Error("query is required");
        }
        const limit = Math.min(Math.max(Number(perPage) || 5, 1), 20);
        const subtype = type ? normalizePostTypeRoute(type).replace(/s$/, "") : undefined;
        const result = await request(buildWpUrl("wp/v2/search", {
          search: search.trim(),
          per_page: limit,
          type: "post",
          subtype,
        }));
        if (!Array.isArray(result)) throw new Error("Unexpected search response");
        return {
          results: result.map((item) => ({
            id: item.id,
            title: plainText(item.title, 200),
            type: item.type,
            subtype: item.subtype,
            url: item.url,
          })),
        };
      },
    },
    {
      name: "wordpress.get_post",
      provider: "wordpress",
      description: "Retrieve a published WordPress post or page. Requires the id or slug of a result returned by wordpress.search_posts.",
      inputSchema: {
        type: "object",
        properties: {
          id: { type: "integer", description: "Post ID from a previous search result." },
          slug: { type: "string", description: "Post slug from a previous search result." },
          post_type: { type: "string", description: "Post type route, e.g. 'posts' or 'pages'. Defaults to 'posts'." },
        },
      },
      execute: async ({ id, slug, post_type: postType = "posts" }) => {
        if (!id && !slug) {
          throw new Error("Either id or slug is required");
        }
        const endpoint = `wp/v2/${normalizePostTypeRoute(postType)}`;
        const post = id
          ? await request(buildWpUrl(`${endpoint}/${encodeURIComponent(id)}`))
          : (await request(buildWpUrl(endpoint, { slug, per_page: 1 })))[0];
        if (!post || typeof post !== "object") throw new Error("No matching WordPress content was found");
        return {
          id: post.id,
          title: plainText(post.title?.rendered, 300),
          content: plainText(post.content?.rendered),
          excerpt: plainText(post.excerpt?.rendered, 1000),
          url: post.link,
          type: post.type,
        };
      },
    },
    {
      name: ACTIONS.searchProducts,
      provider: "woocommerce",
      description: "Search products available in the current WooCommerce store. Always call this before woocommerce.get_product or woocommerce.add_to_cart. The optional category must be a WooCommerce category term ID.",
      inputSchema: {
        type: "object",
        properties: {
          query: { type: "string", description: "Product search terms." },
          category: { type: "string", description: "WooCommerce category term ID (numeric), not a name or slug." },
          per_page: { type: "integer", minimum: 1, maximum: 24, description: "Number of results. Defaults to 6." },
        },
      },
      execute: async ({ query: search, category, per_page: perPage }, action) => {
        const products = await searchProducts(search, category, perPage);
        const result = { products: products.map(productSummary) };
        saveCatalogSearch(result.products, action?.context);
        return result;
      },
    },
    {
      name: ACTIONS.getProduct,
      provider: "woocommerce",
      description: "Retrieve details for one product that was previously returned by woocommerce.search_products. Provide the id or slug from the search results; omit both only when the search returned exactly one product.",
      inputSchema: {
        type: "object",
        properties: {
          id: { type: "integer", description: "Product ID from the previous search results." },
          slug: { type: "string", description: "Product slug from the previous search results." },
        },
      },
      execute: async ({ id, slug }, action) => {
        const search = getCatalogSearch(action?.context);
        if (!search) {
          throw new Error("Search for the product before retrieving its details");
        }
        const products = search.products || [];

        let product = null;
        if (id !== null && id !== undefined) {
          const numericId = Number(id);
          product = products.find((candidate) => Number(candidate.id) === numericId) || null;
          if (!product) {
            throw new Error("The requested product id was not found in the previous search results");
          }
        } else if (slug) {
          const requested = normalizeSlug(slug);
          product = products.find((candidate) => permalinkSlug(candidate.permalink) === requested) || null;
          if (!product) {
            throw new Error("The requested product slug was not found in the previous search results");
          }
        } else if (products.length === 1) {
          product = products[0];
        } else {
          throw new Error("Specify which product from the previous search results should be retrieved");
        }

        const productDetails = await request(buildStoreUrl(`products/${encodeURIComponent(product.id)}`));
        const result = {
          ...productSummary(productDetails),
          variations: await getProductVariations(productDetails),
        };
        saveCatalogProduct(result, action?.context);
        return { product: result };
      },
    },
    {
      name: "woocommerce.get_cart",
      provider: "woocommerce",
      description: "Retrieve the visitor's current WooCommerce cart.",
      inputSchema: { type: "object", properties: {} },
      execute: async () => ({ cart: cartSummary(await storeRequest(buildStoreUrl("cart"))) }),
    },
    {
      name: "woocommerce.add_to_cart",
      provider: "woocommerce",
      classification: "write",
      confirmation: "required",
      description: "Add a previously viewed and purchasable product to the visitor's cart. The product_id must match the product most recently retrieved with woocommerce.get_product. For variable products, a variation_id from the product details is required.",
      inputSchema: {
        type: "object",
        properties: {
          product_id: { type: "integer", description: "ID of the product previously retrieved with woocommerce.get_product." },
          variation_id: { type: "integer", description: "Variation ID for products with options." },
          quantity: { type: "integer", minimum: 1, maximum: MAX_CART_QUANTITY },
        },
        required: ["product_id"],
      },
      execute: async ({ product_id: productId, productId: alternateProductId, id, variation_id: variationId, quantity }, action) => {
        productId = productId ?? alternateProductId ?? id;
        const numericProductId = parsePositiveInteger(productId, "product_id");
        const numericQuantity = validateQuantity(quantity, { minimum: 1 });
        const numericVariationId = parsePositiveInteger(variationId, "variation_id", { required: false });

        const lookup = mostRecentProductLookup(action?.context);
        let product;
        if (lookup) {
          if (!lookup.ok) {
            throw new Error("The requested product could not be found, so it was not added to the cart");
          }
          product = lookup.result?.product;
          const verifiedProductId = Number(product?.id);
          if (!Number.isInteger(verifiedProductId) || verifiedProductId <= 0) {
            throw new Error("The product lookup did not return a valid product ID");
          }
          if (verifiedProductId !== numericProductId) {
            throw new Error(
              "The requested product_id does not match the most recently retrieved product. Retrieve the requested product with woocommerce.get_product first."
            );
          }
        } else {
          product = getCatalogProduct(action?.context);
          if (!product) {
            const search = getCatalogSearch(action?.context);
            if (search?.products?.length === 1) {
              const selected = search.products[0];
              const details = await request(buildStoreUrl(`products/${encodeURIComponent(selected.id)}`));
              product = {
                ...productSummary(details),
                variations: await getProductVariations(details),
              };
              saveCatalogProduct(product, action?.context);
            }
          }
          if (!product) {
            throw new Error("Search for and retrieve a product before adding it to the cart");
          }
          if (Number(product.id) !== numericProductId) {
            throw new Error(
              "The requested product_id does not match the selected product. Retrieve the requested product with woocommerce.get_product first."
            );
          }
        }

        if (product.hasOptions) {
          if (numericVariationId === null) {
            throw new Error("Choose a product variation before adding it to the cart");
          }
          const variation = (product.variations || []).find(
            (candidate) => Number(candidate.id) === numericVariationId
          );
          if (!variation) {
            throw new Error("The selected variation was not found for this product");
          }
          if (!variation.isPurchasable) {
            throw new Error("The selected product variation is not purchasable");
          }
          if (variation.inStock === false) {
            throw new Error("The selected product variation is out of stock");
          }
        } else {
          if (numericVariationId !== null) {
            throw new Error("This product does not use variations; omit variation_id");
          }
          if (!product.isPurchasable && !product.is_purchasable) {
            throw new Error("This product is not currently purchasable");
          }
          if (product.inStock === false) {
            throw new Error("This product is currently out of stock");
          }
        }

        await ensureStoreNonce();
        const cart = await storeRequest(buildStoreUrl("cart/add-item"), {
          method: "POST",
          body: { id: numericVariationId ?? numericProductId, quantity: numericQuantity },
        });
        notifyCartChanged(cart, "added_to_cart");
        return { cart: cartSummary(cart) };
      },
    },
    {
      name: "woocommerce.update_cart",
      provider: "woocommerce",
      classification: "write",
      confirmation: "required",
      description: "Update the quantity of an item in the visitor's WooCommerce cart. A quantity of 0 removes the item.",
      inputSchema: {
        type: "object",
        properties: {
          cart_item_key: { type: "string", minLength: 1, description: "Item key from woocommerce.get_cart." },
          quantity: { type: "integer", minimum: 0, maximum: MAX_CART_QUANTITY },
        },
        required: ["cart_item_key", "quantity"],
      },
      execute: async ({ cart_item_key: key, quantity }) => {
        const validKey = validateCartItemKey(key);
        const numericQuantity = validateQuantity(quantity, { minimum: 0 });
        await ensureStoreNonce();
        const endpoint = numericQuantity === 0 ? "cart/remove-item" : "cart/update-item";
        const body = numericQuantity === 0 ? { key: validKey } : { key: validKey, quantity: numericQuantity };
        const cart = await storeRequest(buildStoreUrl(endpoint), { method: "POST", body });
        notifyCartChanged(cart, "updated_cart_totals");
        return { cart: cartSummary(cart) };
      },
    },
    {
      name: "woocommerce.remove_from_cart",
      provider: "woocommerce",
      classification: "write",
      confirmation: "required",
      description: "Remove an item from the visitor's WooCommerce cart.",
      inputSchema: {
        type: "object",
        properties: {
          cart_item_key: { type: "string", minLength: 1, description: "Item key from woocommerce.get_cart." },
        },
        required: ["cart_item_key"],
      },
      execute: async ({ cart_item_key: key }) => {
        const validKey = validateCartItemKey(key);
        await ensureStoreNonce();
        const cart = await storeRequest(buildStoreUrl("cart/remove-item"), {
          method: "POST",
          body: { key: validKey },
        });
        notifyCartChanged(cart, "removed_from_cart");
        return { cart: cartSummary(cart) };
      },
    },
  ];

  const register = () => {
    const sdk = [window.Sentifyd, window.SentifydRealtime]
      .find((candidate) => typeof candidate?.registerAction === "function");
    if (!sdk) return false;
    const enabledActions = actionDefinitions.filter((action) => available(action.name));
    enabledActions.forEach((action) => sdk.registerAction(action));
    if (enabledActions.length === 0) {
      console.warn("[SentifydWPProvider] SDK found but no actions were enabled by capabilities.");
    }
    debug("Registered actions", enabledActions.map((action) => action.name));
    return true;
  };

  config.register = register;
  if (!register()) {
    window.addEventListener("sentifyd:action-sdk-ready", register, { once: true });
    let attempts = 0;
    const retry = () => {
      if (register() || attempts++ >= 100) return;
      window.setTimeout(retry, 50);
    };
    retry();
  }
})();
