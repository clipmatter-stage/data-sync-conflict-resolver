/**
 * Get current shop parameter from URL
 * @returns {string|null} Shop domain or null
 */
export function getCurrentShop() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('shop');
}

/**
 * Add shop parameter to URL if present in current request
 * @param {string} url - The URL to append shop parameter to
 * @returns {string} URL with shop parameter if shop exists
 */
export function withShopParam(url) {
    const shop = getCurrentShop();
    
    if (!shop) return url;
    
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}shop=${shop}`;
}

/**
 * Add shop parameter to params object if present in current request
 * @param {object} params - The params object to add shop to
 * @returns {object} Params with shop parameter if shop exists
 */
export function withShopParams(params = {}) {
    const shop = getCurrentShop();
    
    if (!shop) return params;
    
    return { ...params, shop };
}
