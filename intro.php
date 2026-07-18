<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SpotLight Studio — Abadikan Momen Berhargamu</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --pink-primary: #e84393;
            --pink-light: #fd79a8;
            --pink-dark: #c2185b;
            --rose-gold: #f5c6b8;
            --white: #ffffff;
            --dark: #0a020a;
        }

        html, body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark);
            width: 100%; min-height: 100vh;
            cursor: default;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        /* ===== VIDEO BACKGROUND ===== */
        .video-bg-container {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 0;
            overflow: hidden;
            background: var(--dark);
        }

        .video-bg {
            position: absolute;
            top: 50%; left: 50%;
            min-width: 100%; min-height: 100%;
            width: auto; height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
            z-index: 0;
            will-change: transform;
        }

        .video-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 1;
            background: linear-gradient(
                180deg,
                rgba(10,2,10,0.25) 0%,
                rgba(10,2,10,0.05) 40%,
                rgba(10,2,10,0.1) 60%,
                rgba(10,2,10,0.5) 100%
            );
            pointer-events: none;
        }

        /* ===== EMOJI RAIN 3D ===== */
        .emoji-rain-container {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 2;
            pointer-events: none;
            overflow: hidden;
        }

        .emoji-drop {
            position: absolute;
            font-size: 2.5rem;
            animation: emoji-fall-3d linear infinite;
            pointer-events: none;
            filter: drop-shadow(0 0 15px rgba(232,67,147,0.6)) drop-shadow(0 4px 8px rgba(0,0,0,0.3));
            will-change: transform;
            transform-style: preserve-3d;
        }

        @keyframes emoji-fall-3d {
            0% {
                transform: translateY(-120px) translateZ(0) rotateX(0deg) rotateY(0deg) scale(0.6);
                opacity: 0;
            }
            5% {
                opacity: 0.9;
                transform: translateY(-80px) translateZ(20px) rotateX(15deg) rotateY(10deg) scale(0.8);
            }
            25% {
                transform: translateY(20vh) translateZ(60px) rotateX(45deg) rotateY(30deg) scale(1);
            }
            50% {
                transform: translateY(50vh) translateZ(100px) rotateX(90deg) rotateY(60deg) scale(1.1);
            }
            75% {
                transform: translateY(80vh) translateZ(60px) rotateX(135deg) rotateY(90deg) scale(1);
            }
            95% {
                opacity: 0.7;
                transform: translateY(105vh) translateZ(20px) rotateX(170deg) rotateY(120deg) scale(0.8);
            }
            100% {
                transform: translateY(120vh) translateZ(0) rotateX(180deg) rotateY(150deg) scale(0.5);
                opacity: 0;
            }
        }

        /* ===== PARTICLE SYSTEM ===== */
        #particles-canvas {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 3;
            pointer-events: none;
        }

        /* ===== MAIN CONTENT ===== */
        .content-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* ===== LOGO SECTION ===== */
        .logo-section {
            text-align: center;
            opacity: 0;
            animation: fade-in-up 1.2s cubic-bezier(0.22, 1, 0.36, 1) 0.5s forwards;
            margin-bottom: 20px;
        }

        .logo-icon-wrapper {
            position: relative;
            width: 100px; height: 100px;
            margin: 0 auto 28px;
        }

        .logo-icon {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, var(--pink-primary), var(--pink-light));
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 60px rgba(232,67,147,0.4), 0 0 100px rgba(232,67,147,0.15);
            animation: logo-float 4s ease-in-out infinite;
            position: relative;
            z-index: 2;
            transform-style: preserve-3d;
        }

        .logo-icon::after {
            content: '';
            position: absolute;
            inset: -5px;
            border-radius: 33px;
            background: linear-gradient(135deg, var(--pink-primary), var(--pink-light), var(--rose-gold), var(--pink-primary));
            background-size: 300% 300%;
            z-index: -1;
            opacity: 0.5;
            animation: gradient-rotate 3s linear infinite;
        }

        @keyframes gradient-rotate {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .logo-icon i {
            font-size: 44px;
            color: white;
            filter: drop-shadow(0 2px 10px rgba(0,0,0,0.4));
            animation: icon-pulse 2.5s ease-in-out infinite;
        }

        @keyframes icon-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        @keyframes logo-float {
            0%, 100% { transform: translateY(0) rotateX(0deg); }
            50% { transform: translateY(-12px) rotateX(5deg); }
        }

        /* ===== TYPING EFFECT ===== */
        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.8rem, 9vw, 6rem);
            font-weight: 700;
            color: var(--white);
            margin-bottom: 10px;
            letter-spacing: 4px;
            line-height: 1.1;
            text-shadow: 0 4px 40px rgba(0,0,0,0.6), 0 0 80px rgba(232,67,147,0.2);
        }

        .brand-name .highlight {
            background: linear-gradient(135deg, var(--pink-primary), var(--pink-light), var(--rose-gold), var(--pink-primary));
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradient-text 4s ease infinite;
        }

        @keyframes gradient-text {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .typing-cursor {
            display: inline-block;
            width: 4px;
            height: 0.9em;
            background: linear-gradient(to bottom, var(--pink-primary), var(--pink-light));
            margin-left: 8px;
            animation: blink 0.7s infinite;
            vertical-align: text-bottom;
            border-radius: 2px;
            box-shadow: 0 0 10px var(--pink-primary);
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        .tagline {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(0.9rem, 2.5vw, 1.3rem);
            font-weight: 300;
            color: rgba(255,255,255,0.8);
            letter-spacing: 5px;
            text-transform: uppercase;
            margin-top: 14px;
            opacity: 0;
            animation: fade-in-up 1.2s cubic-bezier(0.22, 1, 0.36, 1) 2.2s forwards;
            text-shadow: 0 2px 15px rgba(0,0,0,0.4);
        }

        .tagline-sub {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(0.8rem, 2vw, 1.1rem);
            font-weight: 300;
            font-style: italic;
            color: rgba(255,255,255,0.5);
            margin-top: 10px;
            opacity: 0;
            animation: fade-in-up 1.2s cubic-bezier(0.22, 1, 0.36, 1) 2.6s forwards;
            text-shadow: 0 1px 10px rgba(0,0,0,0.3);
        }

        /* ===== SOUND WAVE VISUALIZER ===== */
        .wave-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 60px;
            margin: 30px 0;
            opacity: 0;
            animation: fade-in-up 1.2s cubic-bezier(0.22, 1, 0.36, 1) 3s forwards;
        }

        .wave-bar {
            width: 5px;
            border-radius: 3px;
            background: linear-gradient(to top, var(--pink-primary), var(--pink-light), var(--rose-gold));
            animation: wave-dance 2s ease-in-out infinite;
            transform-origin: bottom;
            box-shadow: 0 0 10px rgba(232,67,147,0.3);
        }

        .wave-bar:nth-child(1) { height: 15px; animation-delay: 0s; }
        .wave-bar:nth-child(2) { height: 32px; animation-delay: 0.18s; }
        .wave-bar:nth-child(3) { height: 48px; animation-delay: 0.36s; }
        .wave-bar:nth-child(4) { height: 38px; animation-delay: 0.54s; }
        .wave-bar:nth-child(5) { height: 55px; animation-delay: 0.72s; }
        .wave-bar:nth-child(6) { height: 28px; animation-delay: 0.9s; }
        .wave-bar:nth-child(7) { height: 45px; animation-delay: 1.08s; }
        .wave-bar:nth-child(8) { height: 22px; animation-delay: 1.26s; }
        .wave-bar:nth-child(9) { height: 50px; animation-delay: 1.44s; }
        .wave-bar:nth-child(10) { height: 35px; animation-delay: 1.62s; }
        .wave-bar:nth-child(11) { height: 42px; animation-delay: 1.8s; }
        .wave-bar:nth-child(12) { height: 18px; animation-delay: 1.98s; }

        @keyframes wave-dance {
            0%, 100% { transform: scaleY(0.3); opacity: 0.3; }
            50% { transform: scaleY(1); opacity: 1; }
        }

        /* ===== CTA BUTTON ===== */
        .cta-section {
            opacity: 0;
            animation: fade-in-up 1.2s cubic-bezier(0.22, 1, 0.36, 1) 3.4s forwards;
            text-align: center;
            position: relative;
            z-index: 10;
            margin-top: 15px;
        }

        .btn-enter {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            padding: 20px 52px;
            background: linear-gradient(135deg, var(--pink-primary), var(--pink-dark));
            color: white;
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 15px 50px rgba(232,67,147,0.45), 0 0 30px rgba(232,67,147,0.15);
            transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }

        .btn-enter::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.7s ease;
        }

        .btn-enter:hover::before {
            left: 100%;
        }

        .btn-enter:hover {
            transform: translateY(-5px) scale(1.04);
            box-shadow: 0 25px 70px rgba(232,67,147,0.55), 0 0 50px rgba(232,67,147,0.25);
        }

        .btn-enter:active {
            transform: translateY(-2px) scale(0.98);
        }

        .btn-enter i {
            font-size: 1.3rem;
            transition: transform 0.3s ease;
        }

        .btn-enter:hover i {
            transform: translateX(8px);
        }

        .hint-text {
            color: rgba(255,255,255,0.45);
            font-size: 0.85rem;
            margin-top: 18px;
            letter-spacing: 1px;
            text-shadow: 0 1px 8px rgba(0,0,0,0.3);
        }

        .hint-text i {
            margin-right: 5px;
            color: var(--pink-light);
            opacity: 0.9;
        }

        /* ===== SCROLL INDICATOR ===== */
        .scroll-indicator {
            position: absolute;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            opacity: 0;
            animation: fade-in-up 1s ease 4s forwards, bounce 2s ease infinite 5s;
            color: rgba(255,255,255,0.45);
            font-size: 0.8rem;
            letter-spacing: 1px;
            text-shadow: 0 1px 5px rgba(0,0,0,0.3);
        }

        .scroll-indicator i {
            font-size: 1.3rem;
            color: var(--pink-light);
        }

        @keyframes bounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(10px); }
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        /* ===== EXIT ANIMATION ===== */
        .exit-animation {
            animation: exit-intro 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards !important;
        }

        @keyframes exit-intro {
            0% { opacity: 1; transform: scale(1); filter: blur(0); }
            100% { opacity: 0; transform: scale(1.15); filter: blur(15px); }
        }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 768px) {
            .content-wrapper { padding: 30px 15px; }
            .logo-icon-wrapper { width: 80px; height: 80px; }
            .logo-icon { border-radius: 22px; }
            .logo-icon i { font-size: 34px; }
            .brand-name { font-size: 2.2rem; letter-spacing: 2px; }
            .tagline { font-size: 0.75rem; letter-spacing: 3px; }
            .tagline-sub { font-size: 0.7rem; }

            .wave-container { gap: 4px; height: 40px; margin: 20px 0; }
            .wave-bar { width: 4px; }

            .btn-enter { padding: 16px 36px; font-size: 1rem; }
            .emoji-drop { font-size: 2rem; }
            .scroll-indicator { display: none; }
        }

        @media (max-width: 480px) {
            .brand-name { font-size: 1.9rem; }
            .tagline { font-size: 0.65rem; letter-spacing: 2px; }
            .wave-container { margin: 15px 0; }
            .hint-text { font-size: 0.7rem; }
            .emoji-drop { font-size: 1.8rem; }
        }

        /* ===== LOADING OVERLAY ===== */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: var(--dark);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.8s ease, visibility 0.8s ease;
        }

        .loading-overlay.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loader {
            width: 60px; height: 60px;
            border: 4px solid rgba(232,67,147,0.2);
            border-top-color: var(--pink-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loader-text {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            margin-top: 20px;
            letter-spacing: 2px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
        <p class="loader-text">Memuat SpotLight...</p>
    </div>

    <!-- Video Background -->
    <div class="video-bg-container">
        <video class="video-bg" id="videoBg" autoplay muted loop playsinline preload="auto" poster="assets/img/spotlight.jpg">
            <source src="assets/img/intro spotlight.MP4" type="video/mp4">
        </video>
    </div>

    <!-- Video Overlay -->
    <div class="video-overlay"></div>

    <!-- Emoji Rain Container -->
    <div class="emoji-rain-container" id="emojiRain"></div>

    <!-- Particles Canvas -->
    <canvas id="particles-canvas"></canvas>

    <!-- Main Content -->
    <div class="content-wrapper" id="introContainer">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon-wrapper">
                <div class="logo-icon">
                    <i class="fas fa-camera-retro"></i>
                </div>
            </div>
            <h1 class="brand-name">
                <span class="highlight" id="typingText"></span><span class="typing-cursor" id="cursor"></span>
            </h1>
            <p class="tagline" id="tagline">Abadikan Setiap Momen Berharga</p>
            <p class="tagline-sub" id="taglineSub">Professional Photography Studio</p>
        </div>

        <!-- Sound Wave Visualizer -->
        <div class="wave-container">
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
            <div class="wave-bar"></div>
        </div>

        <!-- CTA Button -->
        <div class="cta-section">
            <a href="index.php" class="btn-enter" id="enterBtn" onclick="return handleEnter(event)">
                <span>Masuk ke SpotLight</span>
                <i class="fas fa-arrow-right"></i>
            </a>
            <p class="hint-text">
                <i class="fas fa-hand-pointer"></i>
                Klik tombol di atas untuk melanjutkan
            </p>
        </div>

        <!-- Scroll Indicator -->
        <div class="scroll-indicator">
            <span>Gulir ke bawah</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>

    <script>
        // ===== VIDEO OPTIMIZATION =====
        const video = document.getElementById('videoBg');

        // Preload video
        video.addEventListener('loadeddata', function() {
            console.log('Video loaded');
        });

        // Ensure video plays
        video.addEventListener('canplay', function() {
            video.play().catch(function(e) {
                console.log('Autoplay prevented:', e);
            });
        });

        // Smooth playback
        video.playbackRate = 1.0;

        // Buffer strategy
        if (video.buffered.length > 0) {
            console.log('Video buffered:', video.buffered.end(0));
        }

        // ===== HIDE LOADING =====
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loadingOverlay').classList.add('hidden');
            }, 1000);
        });

        // ===== EMOJI RAIN 3D =====
        const emojis = ['🌸', '🎀', '💕', '✨', '📷', '🌺', '💖', '🦋', '🌟', '💗', '🌷', '💝', '✨', '📸', '🌹', '💓', '🌼', '💘', '🌙', '⭐'];
        const emojiContainer = document.getElementById('emojiRain');
        const maxEmojis = 80;
        let currentEmojis = 0;

        function createEmoji() {
            if (currentEmojis >= maxEmojis) {
                // Remove oldest emoji
                const oldest = emojiContainer.querySelector('.emoji-drop');
                if (oldest) {
                    oldest.remove();
                    currentEmojis--;
                }
            }

            const emoji = document.createElement('div');
            emoji.className = 'emoji-drop';
            emoji.textContent = emojis[Math.floor(Math.random() * emojis.length)];
            emoji.style.left = Math.random() * 95 + 2 + '%';
            emoji.style.animationDuration = (Math.random() * 3 + 5) + 's';
            emoji.style.animationDelay = '0s';
            emoji.style.fontSize = (Math.random() * 1.5 + 2) + 'rem';
            emoji.style.opacity = Math.random() * 0.3 + 0.7;
            emoji.style.zIndex = Math.floor(Math.random() * 10);

            emojiContainer.appendChild(emoji);
            currentEmojis++;

            // Auto remove after animation
            setTimeout(function() {
                if (emoji.parentNode) {
                    emoji.remove();
                    currentEmojis--;
                }
            }, 8500);
        }

        // Create emojis continuously
        const emojiInterval = setInterval(createEmoji, 200);

        // Initial burst
        for (let i = 0; i < 15; i++) {
            setTimeout(createEmoji, i * 100);
        }

        // ===== PARTICLE SYSTEM =====
        const canvas = document.getElementById('particles-canvas');
        const ctx = canvas.getContext('2d');

        let particles = [];
        let mouseX = 0, mouseY = 0;
        let isMouseMoving = false;
        let mouseTimeout;
        let animationId;

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        class Particle {
            constructor() {
                this.reset();
            }

            reset() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 0.5;
                this.speedX = (Math.random() - 0.5) * 0.25;
                this.speedY = (Math.random() - 0.5) * 0.25;
                this.opacity = Math.random() * 0.4 + 0.1;
                this.color = this.getRandomColor();
                this.pulse = Math.random() * Math.PI * 2;
                this.pulseSpeed = Math.random() * 0.01 + 0.005;
            }

            getRandomColor() {
                const colors = [
                    'rgba(232, 67, 147, ',
                    'rgba(253, 121, 168, ',
                    'rgba(245, 198, 184, ',
                    'rgba(255, 255, 255, '
                ];
                return colors[Math.floor(Math.random() * colors.length)];
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                this.pulse += this.pulseSpeed;

                if (isMouseMoving) {
                    const dx = mouseX - this.x;
                    const dy = mouseY - this.y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 200) {
                        const force = (200 - dist) / 200;
                        this.x -= dx * force * 0.01;
                        this.y -= dy * force * 0.01;
                    }
                }

                if (this.x < -10) this.x = canvas.width + 10;
                if (this.x > canvas.width + 10) this.x = -10;
                if (this.y < -10) this.y = canvas.height + 10;
                if (this.y > canvas.height + 10) this.y = -10;
            }

            draw() {
                const pulseSize = this.size + Math.sin(this.pulse) * 0.25;
                const pulseOpacity = this.opacity + Math.sin(this.pulse) * 0.05;

                ctx.beginPath();
                ctx.arc(this.x, this.y, pulseSize, 0, Math.PI * 2);
                ctx.fillStyle = this.color + pulseOpacity + ')';
                ctx.fill();

                ctx.beginPath();
                ctx.arc(this.x, this.y, pulseSize * 2, 0, Math.PI * 2);
                ctx.fillStyle = this.color + (pulseOpacity * 0.05) + ')';
                ctx.fill();
            }
        }

        const particleCount = window.innerWidth < 768 ? 25 : 60;
        for (let i = 0; i < particleCount; i++) {
            particles.push(new Particle());
        }

        document.addEventListener('mousemove', function(e) {
            mouseX = e.clientX;
            mouseY = e.clientY;
            isMouseMoving = true;
            clearTimeout(mouseTimeout);
            mouseTimeout = setTimeout(function() { isMouseMoving = false; }, 150);
        });

        function drawConnections() {
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);

                    if (dist < 140) {
                        const opacity = (1 - dist / 140) * 0.08;
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.strokeStyle = 'rgba(232, 67, 147, ' + opacity + ')';
                        ctx.lineWidth = 0.5;
                        ctx.stroke();
                    }
                }
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            particles.forEach(function(p) {
                p.update();
                p.draw();
            });

            drawConnections();
            animationId = requestAnimationFrame(animate);
        }
        animate();

        // ===== TYPING EFFECT =====
        const text = "SpotLight Studio";
        const typingElement = document.getElementById('typingText');
        const cursorElement = document.getElementById('cursor');
        let charIndex = 0;

        function typeText() {
            if (charIndex < text.length) {
                typingElement.textContent += text.charAt(charIndex);
                charIndex++;
                setTimeout(typeText, 100);
            } else {
                cursorElement.style.animation = 'blink 0.7s infinite';
            }
        }

        setTimeout(typeText, 1200);

        // ===== ENTER HANDLER =====
        function handleEnter(e) {
            if (e) e.preventDefault();

            clearInterval(emojiInterval);
            cancelAnimationFrame(animationId);

            const container = document.getElementById('introContainer');
            container.classList.add('exit-animation');

            document.querySelectorAll('.emoji-drop').forEach(function(el) {
                el.style.animation = 'none';
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.3s ease';
            });

            setTimeout(function() {
                window.location.href = 'index.php';
            }, 800);

            return false;
        }

        // ===== KEYBOARD SHORTCUT =====
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleEnter(null);
            }
        });

        // ===== CLEANUP =====
        window.addEventListener('beforeunload', function() {
            clearInterval(emojiInterval);
            cancelAnimationFrame(animationId);
        });
    </script>
</body>
</html>