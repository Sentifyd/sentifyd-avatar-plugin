(() => {
  "use strict";

  const config = window.SentifydWordPressProvider || {};
  const capabilities = new Set(config.capabilities || []);
  const wpRoot = String(config.wpApiRoot || "/wp-json/").replace(/\/?$/, "/");
  const storeRoot = `${wpRoot}wc/store/v1/`;
  let storeNonce = null;

  const available = (name) => capabilities.has(name);

  const request = async (path, options = {}) => {
    const headers = new Headers(options.headers || {});
    headers.set("Accept", "application/json");
    if (options.body) headers.set("Content-Type", "application/json");
    if (storeNonce) headers.set("Nonce", storeNonce);

    const response = await fetch(path, {
      ...options,
      credentials: "same-origin",
      headers,
    });
    const nextNonce = response.headers.get("Nonce");
    if (nextNonce) storeNonce = nextNonce;
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(body.message || `WordPress request failed (${response.status})`);
      error.code = body.code || "PROVIDER_ERROR";
      throw error;
    }
    return body;
  };

  const ensureStoreNonce = async () => {
    if (!storeNonce) await request(`${storeRoot}cart`);
  };

  const query = (parameters) => new URLSearchParams(
    Object.entries(parameters).filter(([, value]) => value !== undefined && value !== null && value !== "")
  ).toString();

  const productSummary = (product) => ({
    id: product.id,
    name: product.name,
    description: product.short_description || product.description || "",
    price: product.prices?.price,
    currency: product.prices?.currency_code,
    inStock: product.is_in_stock,
    permalink: product.permalink,
  });

  const cartSummary = (cart) => ({
    itemCount: cart.items_count,
    total: cart.totals?.total_price,
    currency: cart.totals?.currency_code,
    items: (cart.items || []).map((item) => ({
      key: item.key,
      productId: item.id,
      name: item.name,
      quantity: item.quantity,
      total: item.totals?.line_total,
    })),
  });

  const plainText = (value, limit = 4000) => String(value || "")
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, limit);

  const actionDefinitions = [
    {
      name: "wordpress.search_posts",
      provider: "wordpress",
      description: "Search published WordPress posts and pages.",
      inputSchema: { type: "object", properties: { query: { type: "string" }, post_type: { type: "string" }, per_page: { type: "integer" } }, required: ["query"] },
      execute: async ({ query: search, post_type: type, per_page: perPage }) => {
        const result = await request(`${wpRoot}wp/v2/search?${query({ search, per_page: perPage || 5, type })}`);
        return { results: result.map((item) => ({ id: item.id, title: item.title, type: item.type, subtype: item.subtype, url: item.url })) };
      },
    },
    {
      name: "wordpress.get_post",
      provider: "wordpress",
      description: "Retrieve a published WordPress post or page by ID or slug.",
      inputSchema: { type: "object", properties: { id: { type: "integer" }, slug: { type: "string" }, post_type: { type: "string" } } },
      execute: async ({ id, slug, post_type: postType = "posts" }) => {
        const endpoint = `${wpRoot}wp/v2/${encodeURIComponent(postType)}`;
        const post = id
          ? await request(`${endpoint}/${encodeURIComponent(id)}`)
          : (await request(`${endpoint}?${query({ slug, per_page: 1 })}`))[0];
        if (!post) throw new Error("No matching WordPress content was found");
        return { id: post.id, title: plainText(post.title?.rendered, 300), content: plainText(post.content?.rendered), excerpt: plainText(post.excerpt?.rendered, 1000), url: post.link, type: post.type };
      },
    },
    {
      name: "woocommerce.search_products",
      provider: "woocommerce",
      description: "Search products available in the current WooCommerce store.",
      inputSchema: { type: "object", properties: { query: { type: "string" }, category: { type: "string" }, per_page: { type: "integer" } } },
      execute: async ({ query: search, category, per_page: perPage }) => {
        const products = await request(`${storeRoot}products?${query({ search, category, per_page: perPage || 6 })}`);
        return { products: products.map(productSummary) };
      },
    },
    {
      name: "woocommerce.get_product",
      provider: "woocommerce",
      description: "Retrieve details for one WooCommerce product.",
      inputSchema: { type: "object", properties: { id: { type: "integer" }, slug: { type: "string" } } },
      execute: async ({ id, slug }) => {
        const product = id
          ? await request(`${storeRoot}products/${encodeURIComponent(id)}`)
          : (await request(`${storeRoot}products?${query({ slug, per_page: 1 })}`))[0];
        if (!product) throw new Error("No matching WooCommerce product was found");
        return { product: productSummary(product) };
      },
    },
    {
      name: "woocommerce.get_cart",
      provider: "woocommerce",
      description: "Retrieve the visitor's current WooCommerce cart.",
      inputSchema: { type: "object", properties: {} },
      execute: async () => ({ cart: cartSummary(await request(`${storeRoot}cart`)) }),
    },
    {
      name: "woocommerce.add_to_cart",
      provider: "woocommerce",
      classification: "write",
      confirmation: "required",
      description: "Add a product to the visitor's current WooCommerce cart.",
      inputSchema: { type: "object", properties: { product_id: { type: "integer" }, quantity: { type: "integer", minimum: 1 } }, required: ["product_id"] },
      execute: async ({ product_id: productId, productId: alternateProductId, id, quantity = 1 }) => {
        productId = productId ?? alternateProductId ?? id;
        const numericProductId = Number(productId);
        if (!Number.isInteger(numericProductId) || numericProductId <= 0) {
          throw new Error("A valid product_id is required to add a product to the cart");
        }
        await ensureStoreNonce();
        return { cart: cartSummary(await request(`${storeRoot}cart/add-item`, { method: "POST", body: JSON.stringify({ id: numericProductId, quantity }) })) };
      },
    },
    {
      name: "woocommerce.update_cart",
      provider: "woocommerce",
      classification: "write",
      confirmation: "required",
      description: "Update the quantity of an item in the visitor's WooCommerce cart.",
      inputSchema: { type: "object", properties: { cart_item_key: { type: "string" }, quantity: { type: "integer", minimum: 0 } }, required: ["cart_item_key", "quantity"] },
      execute: async ({ cart_item_key: key, quantity }) => {
        await ensureStoreNonce();
        return { cart: cartSummary(await request(`${storeRoot}cart/update-item`, { method: "POST", body: JSON.stringify({ key, quantity }) })) };
      },
    },
    {
      name: "woocommerce.remove_from_cart",
      provider: "woocommerce",
      classification: "write",
      confirmation: "required",
      description: "Remove an item from the visitor's WooCommerce cart.",
      inputSchema: { type: "object", properties: { cart_item_key: { type: "string" } }, required: ["cart_item_key"] },
      execute: async ({ cart_item_key: key }) => {
        await ensureStoreNonce();
        return { cart: cartSummary(await request(`${storeRoot}cart/remove-item`, { method: "POST", body: JSON.stringify({ key }) })) };
      },
    },
  ];

  const register = () => {
    const sdk = [window.Sentifyd, window.SentifydRealtime]
      .find((candidate) => typeof candidate?.registerAction === "function");
    if (!sdk) return false;
    actionDefinitions.filter((action) => available(action.name)).forEach((action) => sdk.registerAction(action));
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
