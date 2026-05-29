<?php
/**
 * KEY Restaurant & Coffeehouse
 * Frontend Homepage
 */

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/models/Setting.php';
require_once __DIR__ . '/core/models/MenuItem.php';

$settingModel = new Setting();
$menuModel = new MenuItem();

// Get settings
$siteName = $settingModel->get('site_name_fa', 'KEY رستوران و کافه');
$heroTitle = $settingModel->get('hero_title_fa', 'KEY رستوران و کافه');
$heroSubtitle = $settingModel->get('hero_subtitle_fa', 'تجربه‌ای بی‌نظیر از غذا و نوشیدنی');
$ctaText = $settingModel->get('hero_cta_text_fa', 'سفارش آنلاین');
$primaryColor = $settingModel->get('primary_color', '#004647');
$accentColor = $settingModel->get('accent_color', '#D4AF37');

// Get WebGL settings
$webglSettings = $settingModel->getWebGLSettings();

// Get featured menu items
$featuredItems = $menuModel->getFeatured(6);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($heroSubtitle); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: <?php echo $primaryColor; ?>;
            --accent: <?php echo $accentColor; ?>;
            --white: #FFFFFF;
            --black: #0A0A0A;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--black);
            color: var(--white);
            direction: rtl;
            overflow-x: hidden;
        }
        
        /* WebGL Hero Section */
        #hero-section {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }
        
        #webgl-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, rgba(0,70,71,0.3) 0%, rgba(0,0,0,0.7) 100%);
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
        }
        
        .logo-container {
            margin-bottom: 40px;
            animation: fadeInDown 1s ease-out;
        }
        
        .lotus-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }
        
        .lotus-petal {
            fill: var(--accent);
            opacity: 0;
            animation: petalBloom 0.6s ease-out forwards;
        }
        
        .lotus-petal:nth-child(1) { animation-delay: 0.1s; }
        .lotus-petal:nth-child(2) { animation-delay: 0.2s; }
        .lotus-petal:nth-child(3) { animation-delay: 0.3s; }
        .lotus-petal:nth-child(4) { animation-delay: 0.4s; }
        .lotus-petal:nth-child(5) { animation-delay: 0.5s; }
        .lotus-petal:nth-child(6) { animation-delay: 0.6s; }
        .lotus-petal:nth-child(7) { animation-delay: 0.7s; }
        .lotus-petal:nth-child(8) { animation-delay: 0.8s; }
        .lotus-petal:nth-child(9) { animation-delay: 0.9s; }
        
        @keyframes petalBloom {
            from {
                opacity: 0;
                transform: scale(0) rotate(-45deg);
            }
            to {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hero-title {
            font-size: clamp(32px, 8vw, 72px);
            font-weight: 700;
            color: var(--white);
            margin-bottom: 20px;
            text-shadow: 0 4px 20px rgba(0,0,0,0.5);
            animation: fadeInUp 1s ease-out 0.5s both;
        }
        
        .hero-subtitle {
            font-size: clamp(16px, 3vw, 24px);
            color: var(--accent);
            margin-bottom: 40px;
            animation: fadeInUp 1s ease-out 0.7s both;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .glass-button {
            display: inline-block;
            padding: 18px 50px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(212, 175, 55, 0.5);
            border-radius: 50px;
            color: var(--white);
            font-size: 18px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 1s ease-out 0.9s both;
        }
        
        .glass-button:hover {
            background: rgba(212, 175, 55, 0.2);
            border-color: var(--accent);
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(212, 175, 55, 0.3);
        }
        
        .scroll-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            animation: bounce 2s infinite;
            z-index: 2;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            40% {
                transform: translateX(-50%) translateY(-10px);
            }
            60% {
                transform: translateX(-50%) translateY(-5px);
            }
        }
        
        /* Social Links */
        .social-links {
            position: fixed;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .social-link {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            text-decoration: none;
            font-size: 20px;
            transition: all 0.3s;
        }
        
        .social-link:hover {
            background: var(--accent);
            transform: scale(1.1);
        }
        
        /* Menu Section */
        .menu-section {
            padding: 100px 20px;
            background: linear-gradient(180deg, var(--black) 0%, var(--primary) 100%);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            font-size: 48px;
            color: var(--accent);
            margin-bottom: 60px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .menu-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(212, 175, 55, 0.2);
        }
        
        .menu-card-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        
        .menu-card-content {
            padding: 25px;
        }
        
        .menu-card-title {
            font-size: 24px;
            color: var(--white);
            margin-bottom: 10px;
        }
        
        .menu-card-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .menu-card-price {
            font-size: 20px;
            color: var(--accent);
            font-weight: 700;
        }
        
        /* Footer */
        .footer {
            background: var(--black);
            padding: 40px 20px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer p {
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .social-links {
                left: 15px;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section with WebGL -->
    <section id="hero-section">
        <canvas id="webgl-canvas"></canvas>
        <div class="hero-overlay"></div>
        
        <div class="hero-content">
            <div class="logo-container">
                <!-- 9-Petal Lotus Logo -->
                <svg class="lotus-logo" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <g transform="translate(100, 100)">
                        <!-- Center circle -->
                        <circle cx="0" cy="0" r="15" fill="var(--accent)" opacity="0.8"/>
                        
                        <!-- 9 petals arranged in circle -->
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(0)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(40)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(80)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(120)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(160)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(200)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(240)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(280)"/>
                        <path class="lotus-petal" d="M 0,-50 Q 15,-35 0,-20 Q -15,-35 0,-50 Z" transform="rotate(320)"/>
                    </g>
                </svg>
            </div>
            
            <h1 class="hero-title"><?php echo htmlspecialchars($heroTitle); ?></h1>
            <p class="hero-subtitle"><?php echo htmlspecialchars($heroSubtitle); ?></p>
            
            <a href="#menu" class="glass-button"><?php echo htmlspecialchars($ctaText); ?></a>
        </div>
        
        <div class="scroll-indicator">
            <svg width="30" height="50" viewBox="0 0 30 50">
                <rect x="5" y="5" width="20" height="40" rx="10" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2"/>
                <circle cx="15" cy="15" r="3" fill="var(--accent)">
                    <animate attributeName="cy" from="15" to="35" dur="1.5s" repeatCount="indefinite"/>
                </circle>
            </svg>
        </div>
    </section>
    
    <!-- Social Links -->
    <div class="social-links">
        <a href="https://instagram.com/keyrestaurant" class="social-link" target="_blank">📷</a>
        <a href="https://t.me/keyrestaurant" class="social-link" target="_blank">✈️</a>
        <a href="tel:+982112345678" class="social-link">📞</a>
    </div>
    
    <!-- Menu Section -->
    <section id="menu" class="menu-section">
        <div class="container">
            <h2 class="section-title">منوی ویژه</h2>
            
            <div class="menu-grid">
                <?php foreach ($featuredItems as $item): ?>
                    <div class="menu-card">
                        <img src="/uploads/menu/<?php echo htmlspecialchars($item['image'] ?? 'placeholder.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name_fa']); ?>" 
                             class="menu-card-image">
                        <div class="menu-card-content">
                            <h3 class="menu-card-title"><?php echo htmlspecialchars($item['name_fa']); ?></h3>
                            <p class="menu-card-description"><?php echo htmlspecialchars($item['description_fa']); ?></p>
                            <div class="menu-card-price"><?php echo number_format($item['price'], 0); ?> تومان</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> KEY Restaurant & Coffeehouse. All rights reserved.</p>
    </footer>
    
    <!-- WebGL Script -->
    <script>
        // WebGL Hero Scene
        const canvas = document.getElementById('webgl-canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        
        if (!gl) {
            console.error('WebGL not supported');
        } else {
            // Resize canvas
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
                gl.viewport(0, 0, canvas.width, canvas.height);
            }
            
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
            
            // Simple animated gradient background
            let time = 0;
            
            function render() {
                time += 0.01;
                
                // Create animated teal gradient
                const r = 0.0 + Math.sin(time * 0.5) * 0.05;
                const g = 0.27 + Math.sin(time * 0.3) * 0.1;
                const b = 0.28 + Math.sin(time * 0.4) * 0.1;
                
                gl.clearColor(r, g, b, 1.0);
                gl.clear(gl.COLOR_BUFFER_BIT);
                
                requestAnimationFrame(render);
            }
            
            render();
            
            // Mouse interaction
            let mouseX = 0;
            let mouseY = 0;
            
            canvas.addEventListener('mousemove', (e) => {
                mouseX = (e.clientX / window.innerWidth) * 2 - 1;
                mouseY = -(e.clientY / window.innerHeight) * 2 + 1;
            });
        }
        
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
