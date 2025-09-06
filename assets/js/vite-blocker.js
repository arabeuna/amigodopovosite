/**
 * Vite Blocker - Previne requisições indesejadas do Vite
 * Este script bloqueia tentativas de carregamento de recursos do Vite
 * que podem ser injetados por extensões do navegador ou ferramentas de desenvolvimento
 */

(function() {
    'use strict';
    
    // Interceptar requisições XMLHttpRequest
    const originalXHROpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method, url, ...args) {
        if (typeof url === 'string' && url.includes('@vite')) {
            console.warn('Blocked Vite request:', url);
            // Criar uma requisição que falha silenciosamente
            return originalXHROpen.call(this, method, 'data:text/plain,', ...args);
        }
        return originalXHROpen.call(this, method, url, ...args);
    };
    
    // Interceptar requisições fetch
    const originalFetch = window.fetch;
    window.fetch = function(url, ...args) {
        if (typeof url === 'string' && url.includes('@vite')) {
            console.warn('Blocked Vite fetch request:', url);
            // Retornar uma Promise rejeitada silenciosamente
            return Promise.reject(new Error('Vite request blocked'));
        }
        return originalFetch.call(this, url, ...args);
    };
    
    // Interceptar criação de elementos script e link
    const originalCreateElement = document.createElement;
    document.createElement = function(tagName) {
        const element = originalCreateElement.call(this, tagName);
        
        if (tagName.toLowerCase() === 'script') {
            const originalSetAttribute = element.setAttribute;
            element.setAttribute = function(name, value) {
                if (name === 'src' && typeof value === 'string' && value.includes('@vite')) {
                    console.warn('Blocked Vite script:', value);
                    return; // Não definir o src
                }
                return originalSetAttribute.call(this, name, value);
            };
        }
        
        if (tagName.toLowerCase() === 'link') {
            const originalSetAttribute = element.setAttribute;
            element.setAttribute = function(name, value) {
                if (name === 'href' && typeof value === 'string' && value.includes('@vite')) {
                    console.warn('Blocked Vite link:', value);
                    return; // Não definir o href
                }
                return originalSetAttribute.call(this, name, value);
            };
        }
        
        return element;
    };
    
    // Interceptar appendChild para elementos com URLs do Vite
    const originalAppendChild = Node.prototype.appendChild;
    Node.prototype.appendChild = function(child) {
        if (child.tagName === 'SCRIPT' && child.src && child.src.includes('@vite')) {
            console.warn('Blocked Vite script appendChild:', child.src);
            return child; // Retornar o elemento sem adicionar ao DOM
        }
        if (child.tagName === 'LINK' && child.href && child.href.includes('@vite')) {
            console.warn('Blocked Vite link appendChild:', child.href);
            return child; // Retornar o elemento sem adicionar ao DOM
        }
        return originalAppendChild.call(this, child);
    };
    
    console.log('Vite Blocker initialized - Vite requests will be blocked');
})();