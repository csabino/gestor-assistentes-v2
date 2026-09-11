<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - {{ $assistant->name }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .chat-content p { margin-bottom: 0.5rem; }
        .chat-content p:last-child { margin-bottom: 0; }
        .chat-content ul { list-style-type: disc; margin-left: 1.25rem; margin-bottom: 0.5rem; }
        .chat-content ol { list-style-type: decimal; margin-left: 1.25rem; margin-bottom: 0.5rem; }
        .chat-content li { margin-bottom: 0.25rem; }
        .chat-content strong { font-weight: 700; color: #0f172a; }
        .chat-content a { color: #4f46e5; text-decoration: underline; font-weight: 600; word-break: break-all; }
    </style>
</head>
<body class="bg-gray-100 text-slate-800 h-full flex flex-col overflow-hidden">
    <!-- HEADER FIXO -->
    <header class="shrink-0 p-4 bg-white border-b border-gray-200 shadow-sm flex justify-between items-center z-10">
        <h1 class="text-xl font-bold text-indigo-600">Chat com {{ $assistant->name }}</h1>
        <a href="/" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-medium transition text-gray-700">Voltar ao Painel</a>
    </header>

    <!-- MIOLO COM SCROLL -->
    <main id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 max-w-4xl mx-auto w-full">
        <div class="flex justify-start">
            <div class="bg-white text-slate-700 p-3 rounded-lg max-w-[80%] border border-gray-200 shadow-sm chat-content">
                <p>Olá! 😊<br>Seja muito bem-vindo(a)!<br>Sou {{ $assistant->name }}, Assistente Virtual.<br>Como posso ajudar você hoje?</p>
            </div>
        </div>
    </main>

    <!-- FOOTER FIXO -->
    <footer class="shrink-0 p-4 bg-white border-t border-gray-200 z-10">
        <form id="chat-form" class="max-w-4xl mx-auto flex gap-2">
            <input type="text" id="chat-input" placeholder="Digite sua mensagem..." required
                class="flex-1 bg-gray-50 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-800">
            <button type="submit" id="send-btn"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold transition">Enviar</button>
        </form>
    </footer>

    <script>
        const assistantId = "{{ $assistant->id }}";
        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');
        const sendBtn = document.getElementById('send-btn');
        let conversationHistory = [];

        marked.setOptions({
            gfm: true,
            breaks: true
        });

        function appendMessage(role, text) {
            const wrapper = document.createElement('div');
            wrapper.className = role === 'user' ? 'flex justify-end' : 'flex justify-start';

            const bubble = document.createElement('div');
            bubble.className = role === 'user' 
                ? 'bg-indigo-600 text-white p-3 rounded-lg max-w-[80%] shadow-sm' 
                : 'bg-white text-slate-700 p-3 rounded-lg max-w-[80%] border border-gray-200 shadow-sm chat-content';

            if (role === 'assistant') {
                bubble.innerHTML = marked.parse(text);
            } else {
                bubble.innerText = text;
            }

            wrapper.appendChild(bubble);
            chatMessages.appendChild(wrapper);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const messageText = chatInput.value.trim();
            if (!messageText) return;

            appendMessage('user', messageText);
            chatInput.value = '';
            chatInput.disabled = true;
            sendBtn.disabled = true;

            try {
                const response = await fetch(`/chat/${assistantId}/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        message: messageText,
                        history: conversationHistory
                    })
                });

                const rawText = await response.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (e) {
                    throw new Error(`Resposta inválida do servidor (HTTP ${response.status})`);
                }

                if (!response.ok) {
                    throw new Error(data.reply || data.message || `Erro HTTP ${response.status}`);
                }

                const reply = data.reply || 'Sem resposta da IA.';
                appendMessage('assistant', reply);
                
                conversationHistory.push({ role: 'user', content: messageText });
                conversationHistory.push({ role: 'assistant', content: reply });

            } catch (error) {
                appendMessage('assistant', `⚠️ Erro: ${error.message}`);
            } finally {
                chatInput.disabled = false;
                sendBtn.disabled = false;
                chatInput.focus();
            }
        });
    </script>
</body>
</html>