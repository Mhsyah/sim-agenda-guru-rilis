<?php /* Hiasan latar bagian dalam aplikasi (samar): pita biru di sudut kanan atas & gelombang tipis di bawah, senada dengan halaman login. */ ?>
<div class="app-deco no-print" aria-hidden="true">
    <svg class="app-deco-ribbons" viewBox="0 0 520 420" preserveAspectRatio="xMaxYMin meet" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <defs>
            <linearGradient id="ad-deep" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1e3a8a"/><stop offset="1" stop-color="#2563eb"/></linearGradient>
            <linearGradient id="ad-sky" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#38bdf8"/><stop offset="1" stop-color="#93c5fd"/></linearGradient>
        </defs>
        <polygon points="260,0 360,0 520,150 520,245" fill="url(#ad-deep)"/>
        <polygon points="390,0 440,0 520,75 520,122" fill="url(#ad-sky)"/>
        <polygon points="150,0 190,0 520,310 520,350" fill="url(#ad-sky)" opacity=".8"/>
        <line x1="70" y1="0" x2="520" y2="420" stroke="#60a5fa" stroke-width="1.5" opacity=".6"/>
    </svg>
    <svg class="app-deco-waves" viewBox="0 0 1440 200" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <path d="M0,80 C230,30 430,120 690,92 C910,68 1060,140 1440,110 L1440,200 L0,200 Z" fill="#93c5fd" opacity=".55"/>
        <path d="M0,130 C260,90 470,170 770,146 C1010,128 1190,176 1440,160 L1440,200 L0,200 Z" fill="#3b82f6" opacity=".45"/>
        <path d="M0,172 C300,150 530,196 870,182 C1110,172 1270,196 1440,190 L1440,200 L0,200 Z" fill="#1e3a8a" opacity=".5"/>
    </svg>
</div>
