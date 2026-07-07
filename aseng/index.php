<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>Aseng — Pempek Palembang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/menu-uploads/logo_admin.png?v=3">
    <style>
        .bounce-in { animation: bounceIn 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55); }
        .dance:hover { animation: dance 0.4s ease infinite; }
        .float { animation: float 3s ease-in-out infinite; }
        .glow { box-shadow: 0 0 20px rgba(250,204,21,0.25); }
        .tg-pulse { animation: tgPulse 0.6s ease 3; }
        @keyframes bounceIn { 0%{transform:scale(0)} 50%{transform:scale(1.15)} 100%{transform:scale(1)} }
        @keyframes dance { 0%,100%{transform:rotate(0deg)} 25%{transform:rotate(-8deg)} 75%{transform:rotate(8deg)} }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-4px)} }
        @keyframes tgPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.05);box-shadow:0 4px 24px rgba(250,204,21,0.6)} }
        .menu-row { position: relative; overflow: hidden; }
        .menu-row::after {
            content: ''; position: absolute; inset: 0; z-index: 0;
            background-image: var(--bg);
            background-size: cover; background-position: center;
            filter: blur(12px); opacity: 0; transition: opacity 0.35s ease;
        }
        .menu-row:hover::after, .menu-row:active::after { opacity: 0.2; }
        .menu-row > * { position: relative; z-index: 1; }
    </style>
</head>
<body class="bg-zinc-950 text-white font-['Inter',sans-serif] pb-28">
    <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-40 bg-black/80 backdrop-blur-xl border-b border-yellow-500/20">
        <div class="flex items-center justify-between px-5 h-14 max-w-2xl mx-auto">
            <div class="flex items-center gap-3">
                <img src="assets/logo-icon.png?v=2" alt="" class="w-8 h-8 rounded-full object-cover border border-yellow-400/30" onerror="this.style.display='none'">
                <div>
                    <span class="font-['Playfair_Display',serif] text-xl font-bold text-yellow-400">Aseng</span>
                    <span class="text-[10px] text-zinc-500 block -mt-1">Pempek Palembang</span>
                </div>
            </div>
            <button onclick="openCart()" class="relative w-10 h-10 flex items-center justify-center rounded-full bg-zinc-800 border border-zinc-700 hover:bg-yellow-400 hover:text-black transition-all duration-300 float">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="absolute -top-1 -right-1 bg-yellow-400 text-black text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center hidden bounce-in" id="badge">0</span>
            </button>
        </div>
    </header>

    <!-- Hero -->
    <section class="pt-14 bg-gradient-to-br from-zinc-900 via-black to-zinc-950 text-white px-5 pb-10 relative overflow-hidden border-b border-yellow-500/10">
        <div class="absolute top-10 right-0 w-64 h-64 bg-yellow-400/5 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-10 -left-10 w-48 h-48 bg-yellow-400/5 rounded-full blur-3xl"></div>
        <div class="max-w-2xl mx-auto pt-8 relative">
            <div class="inline-flex items-center gap-2 bg-yellow-400/10 px-4 py-1.5 rounded-full text-xs mb-4 backdrop-blur-sm border border-yellow-400/20 text-yellow-300">
                <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                Pempek Asli Palembang — Wong Kito Galo!
            </div>
            <h1 class="font-['Playfair_Display',serif] text-4xl font-bold leading-tight">
                Pempek Kito<br>
                <span class="text-yellow-400">Dijamin Merem Melek!</span>
            </h1>
            <p class="text-zinc-400 text-sm mt-2 max-w-xs">Pempek tenggiri asli — ikannya berasa banget di lidah! 🐟 Cuko hitam kental, pedes manis asam seger, bikin nagih.</p>
            <div class="flex gap-3 mt-5">
                <a href="#menu" class="inline-flex items-center gap-2 bg-yellow-400 text-black font-bold px-6 py-3 rounded-full text-sm hover:scale-105 transition-transform shadow-lg shadow-yellow-400/20 glow">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    Pesan Sekarang
                </a>
                <a href="https://t.me/pempektitilkps" target="_blank" class="inline-flex items-center gap-2 bg-white/5 text-zinc-300 px-6 py-3 rounded-full text-sm backdrop-blur-sm border border-white/10 hover:bg-white/10 transition-all">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    Chat
                </a>
            </div>
        </div>
    </section>

    <!-- Menu List -->
    <main class="max-w-2xl mx-auto px-5 -mt-2" id="menu">
        <div id="menu-list">
            <div class="text-center py-8 text-zinc-600 text-sm">Loading menu...</div>
        </div>
    </main>

    <!-- Cart Drawer -->
    <div class="fixed inset-0 z-50 invisible" id="cart-drawer">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeCart()"></div>
        <div class="absolute bottom-0 left-0 right-0 bg-zinc-900/60 backdrop-blur-xl rounded-t-3xl h-[92vh] flex flex-col translate-y-full transition-transform duration-500 ease-out shadow-2xl border-t border-zinc-700/50" id="cart-sheet">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800">
                <span class="font-semibold text-lg flex items-center gap-2">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#facc15" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    Your Order
                </span>
                <button onclick="closeCart()" class="text-2xl leading-none text-zinc-500 hover:text-white transition-colors">×</button>
            </div>
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-1" id="cart-body">
                <div class="text-center py-10 text-zinc-500 text-sm">Your cart is empty</div>
            </div>
            <div class="px-5 py-4 border-t border-zinc-800 space-y-3 bg-zinc-900/60" id="cart-footer"></div>
        </div>
    </div>

    <!-- Food Drawer -->
    <div class="fixed inset-0 z-[60] invisible" id="food-drawer">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300" id="food-bg" onclick="closeFoodDrawer()"></div>
        <div class="absolute bottom-0 left-0 right-0 bg-zinc-900 rounded-t-3xl translate-y-full transition-transform duration-500 ease-out max-h-[85vh] overflow-y-auto shadow-2xl border-t border-zinc-700/50" id="food-sheet">
            <div class="w-10 h-1 rounded-full bg-zinc-600/40 mx-auto mt-3 mb-1"></div>
            <div class="w-full max-h-[50vh] bg-zinc-800 flex items-center justify-center overflow-hidden">
                <img id="food-img" src="" alt="" class="w-full h-auto max-h-[50vh] object-contain">
            </div>
            <div class="p-5 pb-10">
                <div class="text-xs uppercase tracking-wider font-semibold text-yellow-400" id="food-cat"></div>
                <div class="text-xl font-bold mt-1 font-['Playfair_Display',serif]" id="food-name"></div>
                <div class="text-lg font-bold text-yellow-400 mt-1" id="food-price"></div>
            </div>
        </div>
    </div>

    <!-- Order Confirm Modal -->
    <div class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/40 backdrop-blur-sm" id="order-confirm">
        <div class="bg-zinc-900 backdrop-blur-xl rounded-3xl p-6 mx-4 max-w-md w-full shadow-2xl border border-zinc-700/30 bounce-in max-h-[90vh] overflow-y-auto">
            <div class="w-14 h-14 rounded-full bg-yellow-400/90 flex items-center justify-center mx-auto mb-3 shadow-lg shadow-yellow-400/20">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2 class="text-xl font-bold font-['Playfair_Display',serif] text-yellow-400">Pesanan Diterima!</h2>
            <p class="text-xs text-zinc-400 mt-1">Order <strong id="confirm-num" class="text-white"></strong></p>
            
            <!-- Bill breakdown -->
            <div class="mt-4 pt-4 border-t border-zinc-700/40" id="confirm-bill"></div>
            
            <p class="text-[10px] text-zinc-500 mt-3">Kami akan siapkan pesananmu segera.</p>
            <div class="space-y-2 mt-4">
                <a id="tg-direct" href="https://t.me/pempektitilkps" target="_blank" class="block w-full bg-yellow-400 text-black font-bold py-3 rounded-full flex items-center justify-center gap-2 hover:scale-105 transition-transform glow text-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    Chat via Telegram
                </a>
                <button onclick="document.getElementById('order-confirm').classList.add('hidden')" class="w-full text-sm text-zinc-400 py-2 hover:text-white transition-colors">Lanjut Belanja</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="fixed bottom-24 left-1/2 -translate-x-1/2 bg-zinc-800/95 backdrop-blur-xl text-white px-5 py-2.5 rounded-full shadow-lg text-sm font-medium z-[80] transition-all duration-300 opacity-0 translate-y-4 border border-zinc-700/50" id="toast"></div>

    <!-- Chat Bubble -->
    <button onclick="toggleChat()" class="fixed bottom-20 right-5 z-[55] w-12 h-12 rounded-full bg-zinc-800/95 backdrop-blur-xl border border-zinc-700/50 shadow-lg flex items-center justify-center hover:bg-yellow-400 hover:text-black transition-all duration-300 float" id="chat-bubble" aria-label="Chat">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    </button>

    <!-- Chat Widget -->
    <div class="fixed bottom-36 right-5 z-[55] w-80 max-w-[calc(100vw-2rem)] bg-zinc-900/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-zinc-700/50 hidden" id="chat-widget">
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
            <div class="flex items-center gap-2">
                <img src="assets/logo-icon.png?v=2" alt="" class="w-8 h-8 rounded-full object-cover border border-zinc-700" onerror="this.style.display='none'">
                <div>
                    <div class="text-sm font-semibold text-white">Aseng Support</div>
                    <div class="text-[10px] text-green-500 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Online</div>
                </div>
            </div>
            <button onclick="toggleChat()" class="text-zinc-500 hover:text-white transition-colors">×</button>
        </div>
        <div class="h-64 overflow-y-auto px-4 py-3 space-y-3" id="chat-msgs">
            <div class="flex">
                <div class="bg-zinc-800 rounded-2xl rounded-bl-sm px-3 py-2 text-sm text-zinc-200 max-w-[80%]">Hi there! Welcome to Aseng. How can we help?</div>
            </div>
        </div>
        <div class="flex items-center gap-2 px-4 py-3 border-t border-zinc-800">
            <input id="chat-input" type="text" placeholder="Type a message..." maxlength="500" class="flex-1 bg-zinc-800 rounded-full px-4 py-2 text-sm text-white outline-none placeholder-zinc-500 focus:ring-1 focus:ring-yellow-400/30">
            <button onclick="sendChatMsg()" class="w-9 h-9 rounded-full bg-yellow-400 flex items-center justify-center shrink-0 hover:scale-105 transition-transform">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
    </div>

    <script src="assets/app.js?v=12"></script>
    
    <!-- Footer -->
    <footer class="max-w-2xl mx-auto px-5 pb-8 pt-4 mt-6 border-t border-zinc-800/50">
        <div class="flex items-center gap-4 mb-3">
            <img src="assets/logo-icon.png?v=2" alt="Aseng" class="w-12 h-12 rounded-full object-cover border border-zinc-700">
            <div>
                <div class="font-['Playfair_Display',serif] text-lg font-bold text-white">Aseng</div>
                <div class="text-[10px] text-zinc-500">Pempek Palembang · Authentic Taste</div>
            </div>
        </div>
        <a href="https://t.me/pempektitilkps" target="_blank" class="inline-flex items-center gap-2 text-sm text-zinc-400 hover:text-yellow-400 transition-colors">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
            @pempektitilkps
        </a>
    </footer>
</body>
</html>
