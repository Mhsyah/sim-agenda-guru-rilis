<?php /* Latar halaman login: pita diagonal & gelombang biru, selaras dengan sampul Buku Agenda Guru. */ ?>
<div class="auth-bg" aria-hidden="true">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMaxYMax slice" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <defs>
            <linearGradient id="ab-deep" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1e3a8a"/><stop offset="1" stop-color="#1d4ed8"/></linearGradient>
            <linearGradient id="ab-mid" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2563eb"/><stop offset="1" stop-color="#3b82f6"/></linearGradient>
            <linearGradient id="ab-sky" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#38bdf8"/><stop offset="1" stop-color="#7dd3fc"/></linearGradient>
            <linearGradient id="ab-w1" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#93c5fd"/><stop offset="1" stop-color="#bfdbfe"/></linearGradient>
            <linearGradient id="ab-w2" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#3b82f6"/><stop offset="1" stop-color="#60a5fa"/></linearGradient>
            <linearGradient id="ab-w3" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#1e3a8a"/><stop offset=".6" stop-color="#1d4ed8"/><stop offset="1" stop-color="#2563eb"/></linearGradient>
        </defs>

        <!-- pita diagonal kanan atas -->
        <g class="ab-ribbons">
            <polygon points="1000,0 1170,0 1440,250 1440,410" fill="url(#ab-deep)"/>
            <polygon points="1210,0 1290,0 1440,140 1440,215" fill="url(#ab-mid)"/>
            <polygon points="860,0 920,0 1440,470 1440,530" fill="url(#ab-sky)" opacity=".85"/>
            <polygon points="1320,0 1440,0 1440,95" fill="url(#ab-sky)"/>
            <line x1="760" y1="0" x2="1440" y2="615" stroke="#60a5fa" stroke-width="2" opacity=".55"/>
            <line x1="1100" y1="0" x2="1440" y2="300" stroke="#bfdbfe" stroke-width="1.5" opacity=".7"/>
        </g>

        <!-- pita diagonal kiri bawah -->
        <g class="ab-ribbons">
            <polygon points="0,560 0,640 300,900 210,900" fill="url(#ab-sky)" opacity=".7"/>
            <line x1="0" y1="470" x2="470" y2="900" stroke="#60a5fa" stroke-width="2" opacity=".45"/>
        </g>

        <!-- gelombang bawah -->
        <path class="ab-w1" d="M0,650 C230,575 430,710 690,668 C910,632 1060,735 1440,700 L1440,900 L0,900 Z" fill="url(#ab-w1)"/>
        <path class="ab-w2" d="M0,735 C260,665 470,800 770,760 C1010,728 1190,812 1440,790 L1440,900 L0,900 Z" fill="url(#ab-w2)"/>
        <path class="ab-w3" d="M0,812 C300,770 530,872 870,842 C1110,820 1270,880 1440,868 L1440,900 L0,900 Z" fill="url(#ab-w3)"/>
    </svg>
</div>
