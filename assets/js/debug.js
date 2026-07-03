/**
 * EUREKA LABS - DEBUG UTILITY
 * Inject this into pages to catch exact network errors.
 */

async function debugFetch(url, options) {
    console.log(`[DEBUG] A tentar ligar a: ${url}`);
    console.log(`[DEBUG] Opções:`, options);
    
    try {
        const start = Date.now();
        const response = await fetch(url, options);
        const duration = Date.now() - start;
        
        console.log(`[DEBUG] Resposta recebida em ${duration}ms`);
        console.log(`[DEBUG] Status: ${response.status} ${response.statusText}`);
        
        if (!response.ok) {
            const text = await response.text();
            console.error(`[DEBUG] Erro do Servidor:`, text);
            throw new Error(`HTTP ${response.status}: ${text}`);
        }
        
        const data = await response.json();
        console.log(`[DEBUG] Dados JSON:`, data);
        return data;
    } catch (error) {
        console.error(`[DEBUG] FALHA TOTAL DE REDE:`);
        console.error(`- Mensagem: ${error.message}`);
        console.error(`- Nome: ${error.name}`);
        console.error(`- Stack: ${error.stack}`);
        
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            console.error(`[DEBUG] CAUSA PROVÁVEL: Bloqueio de CORS, Protocolo Misto (HTTP/HTTPS) ou URL inexistente.`);
        }
        
        throw error;
    }
}

// Substituir o fetch global por esta versão de debug (opcional)
// window.fetch = debugFetch;
