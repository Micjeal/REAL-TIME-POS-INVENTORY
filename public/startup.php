<?php
// Include configuration (handles session start)
require_once 'config.php';

// --- Security Headers ---
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
// Set site name if not defined
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'MTECH UGANDA');
}
// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="MTECH UGANDA - Streamline your business operations with our comprehensive business management system">
    <meta name="theme-color" content="#1e2130">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="application-name" content="<?php echo SITE_NAME; ?>">
    <title>Welcome to <?php echo SITE_NAME; ?> | Business Management Solution</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    
    <!-- Preload critical assets -->
    <link rel="preconnect" href="https://stackpath.bootstrapcdn.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://code.jquery.com">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --dark-bg: #1e2130;
            --med-bg: #2a2e43;
            --light-bg: #3a3f55;
            --text-light: #f0f0f0;
            --text-muted: #a0a0a0;
            --accent-blue: #3584e4;
            --accent-green: #2fac66;
            --accent-red: #e35d6a;
            --card-shadow: 0 8px 16px rgba(0,0,0,0.2);
            --transition-speed: 0.3s;
            --border-radius: 8px;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--dark-bg);
            background-image: linear-gradient(135deg, #1e2130 0%, #2d3142 100%);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            position: relative;
        }
        
        ::selection {
            background-color: var(--accent-blue);
            color: white;
        }
        
        .header {
            background-color: var(--med-bg);
            padding: 1.25rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            position: relative;
            z-index: 10;
            backdrop-filter: blur(8px);
        }
        
        .logo-container {
            text-align: center;
            margin: 2rem auto;
            animation: fadeInDown 0.8s ease-out;
        }
        
        .logo {
            max-width: 200px;
            margin-bottom: 1rem;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
            transition: transform var(--transition-speed);
        }
        
        .logo:hover {
            transform: translateY(-5px);
        }
        
        .hero-section {
            padding: 5rem 0 4rem;
            text-align: center;
            background-color: var(--med-bg);
            background-image: linear-gradient(180deg, var(--med-bg) 0%, var(--dark-bg) 100%);
            margin-bottom: 4rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--accent-blue), transparent);
            opacity: 0.6;
        }
        
        .hero-title {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(120deg, #ffffff, #d0d0d0);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }
        
        .hero-subtitle {
            font-size: 1.35rem;
            line-height: 1.5;
            margin-bottom: 2.5rem;
            color: var(--text-muted);
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
        
        .login-btn {
            background-color: var(--accent-blue);
            color: white;
            border: none;
            padding: 0.85rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 500;
            border-radius: 50px;
            transition: all 0.3s cubic-bezier(.25,.8,.25,1);
            margin-top: 1.5rem;
            box-shadow: 0 4px 12px rgba(53,132,228,0.25);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
            animation: fadeInUp 0.8s ease-out 0.6s both;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(53,132,228,0.35);
            background-color: #2a75d0;
        }
        
        .login-btn:active {
            transform: translateY(1px) scale(0.98);
        }
        
        .login-btn .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
            vertical-align: middle;
            margin-left: 0.5rem;
            border-width: 2px;
        }
        
        .login-btn.loading {
            pointer-events: none;
            opacity: 0.8;
            background-color: #2970c5;
        }
        
        .login-btn i {
            transition: transform 0.3s ease;
        }
        
        .login-btn:hover i {
            transform: translateX(4px);
        }
        .footer {
            background-color: var(--med-bg);
            color: var(--text-muted);
            text-align: center;
            padding: 1rem 0 0.5rem 0;
            margin-top: auto;
            font-size: 0.95rem;
        }
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(26,29,41,0.97);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .loading-container {
            background: var(--med-bg);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            text-align: center;
            max-width: 90%;
            width: 400px;
            position: relative;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .spinner-container {
            margin-bottom: 1.5rem;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.25rem;
            color: var(--accent-blue);
        }
        .loading-status {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-top: 0.5rem;
            letter-spacing: 0.5px;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }
        
        .status-title {
            color: var(--text-light);
            font-size: 1.4rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .status-highlight {
            color: var(--accent-blue);
            font-weight: 600;
        }
        
        .loading-progress {
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            margin: 1.5rem 0 0.5rem;
            overflow: hidden;
            position: relative;
        }
        
        .loading-progress-bar {
            height: 100%;
            background: var(--accent-blue);
            border-radius: 2px;
            width: 0;
            transition: width 0.8s ease;
        }
        @media (max-width: 600px) {
            .hero-title { font-size: 2rem; }
            .hero-section { padding: 2rem 0; }
            .logo { max-width: 120px; }
        }
        /* Subtle hero animation */
        .hero-section {
            animation: fadeInHero 1.2s cubic-bezier(.4,2,.3,1);
        }
        @keyframes fadeInHero {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .features-section {
            padding: 4rem 0;
            position: relative;
        }
        
        .features-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--accent-blue);
            border-radius: 2px;
        }
        
        .feature-card {
            background-color: var(--med-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            height: 100%;
            margin-bottom: 2rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            transition: all var(--transition-speed);
            border: 1px solid rgba(255,255,255,0.03);
            position: relative;
            overflow: hidden;
            transform: translateY(0);
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.08);
        }
        
        .feature-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 0;
            background: var(--accent-blue);
            transition: height 0.4s ease;
        }
        
        .feature-card:hover::after {
            height: 100%;
        }
        
        .feature-icon {
            font-size: 2.25rem;
            color: var(--accent-blue);
            margin-bottom: 1.25rem;
            transition: transform 0.3s ease;
            display: inline-block;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1);
        }
        
        .feature-title {
            font-size: 1.35rem;
            margin-bottom: 1rem;
            font-weight: 600;
            position: relative;
            display: inline-block;
        }
        
        .feature-text {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .row-1 .feature-card {
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }
        
        .row-2 .feature-card {
            animation: fadeInUp 0.8s ease-out 0.9s both;
        }
        
        .footer {
            background-color: var(--med-bg);
            padding: 2rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        
        .footer p {
            margin-bottom: 0;
            color: var(--text-muted);
        }
        
        .footer-logo {
            max-width: 120px;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .footer-links {
            margin-bottom: 1rem;
        }
        
        .footer-links a {
            color: var(--text-muted);
            margin: 0 10px;
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 0.9rem;
        }
        
        .footer-links a:hover {
            color: var(--text-light);
        }
        
        .version-info {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.4);
        }
        
        /* About Section Styles */
        .about-section {
            padding: 5rem 0;
            position: relative;
            background: linear-gradient(to bottom, var(--dark-bg), var(--med-bg));
            overflow: hidden;
        }
        
        /* Stars Background Effect - Small Stars Layer */
        .about-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(1.5px 1.5px at 40px 60px, rgba(255, 255, 255, 0.9) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1px 1px at 20px 50px, rgba(255, 255, 255, 0.8) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1.5px 1.5px at 110px 110px, rgba(255, 255, 255, 0.7) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1px 1px at 220px 280px, rgba(255, 255, 255, 0.85) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1.5px 1.5px at 350px 80px, rgba(255, 255, 255, 0.75) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1px 1px at 390px 300px, rgba(255, 255, 255, 0.8) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1.5px 1.5px at 500px 200px, rgba(255, 255, 255, 0.7) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1px 1px at 15px 250px, rgba(255, 255, 255, 0.9) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1.5px 1.5px at 170px 330px, rgba(255, 255, 255, 0.8) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(1px 1px at 270px 220px, rgba(255, 255, 255, 0.7) 50%, rgba(0, 0, 0, 0));
            background-repeat: repeat;
            background-size: 600px 600px;
            opacity: 0.2;
            animation: starsAnimation 150s linear infinite;
            z-index: 1;
        }
        
        /* Medium Stars Layer with Glow */
        .about-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(4px 4px at 150px 120px, rgba(255, 255, 255, 0.9) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(6px 6px at 410px 280px, rgba(89, 196, 255, 0.8) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(5px 5px at 290px 190px, rgba(88, 173, 255, 0.7) 50%, rgba(0, 0, 0, 0)),
                radial-gradient(5px 5px at 320px 40px, rgba(99, 185, 255, 0.85) 50%, rgba(0, 0, 0, 0));
            background-repeat: repeat;
            background-size: 800px 800px;
            filter: blur(3px);
            opacity: 0.15;
            animation: starsGlow 8s ease-in-out infinite alternate, starsAnimation 120s linear infinite reverse;
            z-index: 1;
        }
        
        /* Cursor Glow Effects */
        .cursor-glow {
            position: absolute;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(53, 132, 228, 0.35) 0%, rgba(47, 172, 102, 0.2) 40%, rgba(0, 0, 0, 0) 70%);
            filter: blur(15px);
            pointer-events: none;
            z-index: 2;
            opacity: 0;
            transition: opacity 0.3s ease;
            mix-blend-mode: screen;
        }
        
        .cursor-glow::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.25) 0%, rgba(89, 196, 255, 0.15) 30%, rgba(0, 0, 0, 0) 70%);
            filter: blur(10px);
            animation: pulseGlowInner 3s ease-in-out infinite alternate;
        }
        
        .cursor-glow::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.4) 0%, rgba(53, 132, 228, 0.25) 30%, rgba(0, 0, 0, 0) 70%);
            filter: blur(5px);
            animation: pulseGlowInner 2s ease-in-out infinite alternate;
        }
        
        .about-section.hover-active .highlight-item {
            transition: transform 0.3s ease-out, box-shadow 0.3s ease-out;
        }
        
        .about-section.hover-active .highlight-item:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .about-section.hover-active .highlight-icon {
            animation: iconGlow 3s ease-in-out infinite alternate, pulseGlow 2s linear infinite;
        }
        
        @keyframes pulseGlowInner {
            0% {
                opacity: 0.4;
                width: 100%;
                height: 100%;
            }
            100% {
                opacity: 0.8;
                width: 80%;
                height: 80%;
            }
        }
        
        /* Large Stars Layer with Strong Glow */
        .about-section .container {
            position: relative;
            z-index: 3;
        }
        
        .about-section .container::before {
            content: '';
            position: absolute;
            top: -100px;
            left: -100px;
            right: -100px;
            bottom: -100px;
            background-image: 
                radial-gradient(8px 8px at 250px 120px, rgba(255, 255, 255, 0.95) 10%, rgba(53, 132, 228, 0.4) 30%, rgba(0, 0, 0, 0) 70%),
                radial-gradient(10px 10px at 550px 380px, rgba(255, 255, 255, 0.95) 10%, rgba(89, 196, 255, 0.6) 30%, rgba(0, 0, 0, 0) 70%),
                radial-gradient(12px 12px at 150px 350px, rgba(255, 255, 255, 0.9) 10%, rgba(47, 172, 102, 0.5) 30%, rgba(0, 0, 0, 0) 70%),
                radial-gradient(15px 15px at 450px 50px, rgba(255, 255, 255, 0.95) 10%, rgba(99, 185, 255, 0.6) 30%, rgba(0, 0, 0, 0) 70%);
            background-repeat: repeat;
            background-size: 1000px 1000px;
            filter: blur(5px);
            opacity: 0.2;
            animation: starsGlow3D 10s ease-in-out infinite alternate, starsAnimation 180s linear infinite;
            z-index: -1;
            pointer-events: none;
        }
        
        .section-header {
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 2;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-light);
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--accent-blue);
            border-radius: 1.5px;
        }
        
        .section-subtitle {
            font-size: 1.2rem;
            color: var(--text-muted);
            max-width: 700px;
            margin: 1rem auto 0;
        }
        
        .about-content {
            padding: 2rem;
            background: rgba(58, 63, 85, 0.3);
            border-radius: var(--border-radius);
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: var(--card-shadow);
            animation: fadeInLeft 0.8s ease-out both;
        }
        
        .about-content h3 {
            font-size: 1.6rem;
            margin-bottom: 1.25rem;
            color: var(--text-light);
            font-weight: 600;
        }
        
        .about-content p {
            color: var(--text-muted);
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .feature-list li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
            color: var(--text-light);
        }
        
        .feature-list li i {
            color: var(--accent-green);
            margin-right: 0.75rem;
            font-size: 1.1rem;
            margin-top: 0.2rem;
        }
        
        .feature-list li span {
            flex: 1;
            line-height: 1.5;
        }
        
        .system-highlight {
            padding: 2rem;
            background: rgba(58, 63, 85, 0.3);
            border-radius: var(--border-radius);
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: var(--card-shadow);
            animation: fadeInRight 0.8s ease-out both;
        }
        
        .system-highlight h3 {
            font-size: 1.6rem;
            margin-bottom: 1.5rem;
            color: var(--text-light);
            font-weight: 600;
        }
        
        .highlight-item {
            display: flex;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .highlight-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .highlight-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), #1d65c1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 15px rgba(53, 132, 228, 0.5),
                       0 0 20px rgba(53, 132, 228, 0.4),
                       inset 0 0 8px rgba(255, 255, 255, 0.4);
            position: relative;
            transition: all 0.3s ease;
            animation: iconGlow 3s ease-in-out infinite alternate;
            z-index: 2;
        }
        
        .highlight-icon::after {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            background: transparent;
            border: 2px solid rgba(53, 132, 228, 0.2);
            box-shadow: 0 0 15px rgba(53, 132, 228, 0.5);
            animation: pulseGlow 2s linear infinite;
            z-index: 1;
        }
        
        @keyframes iconGlow {
            0% {
                box-shadow: 0 5px 15px rgba(53, 132, 228, 0.5),
                           0 0 20px rgba(53, 132, 228, 0.4),
                           inset 0 0 8px rgba(255, 255, 255, 0.4);
            }
            100% {
                box-shadow: 0 5px 15px rgba(53, 132, 228, 0.7),
                           0 0 30px rgba(53, 132, 228, 0.6),
                           0 0 50px rgba(53, 132, 228, 0.3),
                           inset 0 0 12px rgba(255, 255, 255, 0.6);
            }
        }
        
        @keyframes pulseGlow {
            0% {
                transform: scale(1);
                opacity: 0.5;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.2;
            }
            100% {
                transform: scale(1);
                opacity: 0.5;
            }
        }
        
        .highlight-content h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--text-light);
        }
        
        .highlight-content p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 0;
        }
        
        .system-benefits {
            margin-top: 2rem;
            padding: 2.5rem;
            background: rgba(58, 63, 85, 0.2);
            border-radius: var(--border-radius);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: var(--card-shadow);
            animation: fadeInUp 0.8s ease-out 0.3s both;
        }
        
        .system-benefits h3 {
            font-size: 1.8rem;
            color: var(--text-light);
            margin-bottom: 2rem;
        }
        
        .benefit-item {
            text-align: center;
            padding: 1.5rem;
            height: 100%;
            transition: transform var(--transition-speed);
        }
        
        .benefit-item:hover {
            transform: translateY(-8px);
        }
        
        .benefit-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), #1d65c1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            color: white;
            font-size: 1.75rem;
            box-shadow: 0 8px 20px rgba(29, 101, 193, 0.3);
        }
        
        .benefit-item h4 {
            font-size: 1.3rem;
            margin-bottom: 0.75rem;
            color: var(--text-light);
        }
        
        .benefit-item p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        /* CTA Section Styles */
        .cta-container {
            background-color: var(--light-bg);
            padding: 3.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            margin: 5rem 0 4rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out both;
        }
        
        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes starsAnimation {
            from { background-position: 0 0; }
            to { background-position: 600px 600px; }
        }
        
        @keyframes starsGlow {
            0% { opacity: 0.05; filter: blur(4px); }
            50% { opacity: 0.1; filter: blur(5px); }
            100% { opacity: 0.08; filter: blur(3px); }
        }
        
        @keyframes starsGlow3D {
            0% { opacity: 0.15; filter: blur(5px); transform: translateZ(0); }
            25% { opacity: 0.25; filter: blur(7px); transform: translateZ(5px); }
            50% { opacity: 0.2; filter: blur(6px); transform: translateZ(3px); }
            75% { opacity: 0.3; filter: blur(8px); transform: translateZ(8px); }
            100% { opacity: 0.25; filter: blur(7px); transform: translateZ(5px); }
        }
            
            .hero-subtitle {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay for login -->
    <div class="loading-overlay" id="loadingOverlay" aria-live="polite" aria-label="Loading">
        <div class="loading-container">
            <h3 class="status-title">Logging you in</h3>
            <div class="spinner-container">
                <div class="spinner-border" role="status" aria-hidden="true"></div>
            </div>
            <div class="loading-status" id="loadingStatus">Connecting to system...</div>
            <div class="loading-progress">
                <div class="loading-progress-bar" id="progressBar"></div>
            </div>
        </div>
    </div>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><?php echo SITE_NAME; ?></h1>
                </div>
                <div>
                    <a href="login.php" class="btn login-btn">Login</a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?> Logo" class="logo" onerror="this.onerror=null;this.src='assets/images/logo.svg';">
            </div>
            <h1 class="hero-title">Welcome to <?php echo SITE_NAME; ?> Point of Sale System</h1>
            <p class="hero-subtitle">A comprehensive solution for modern retail and business management</p>
            <a href="login.php" class="btn login-btn btn-lg">Get Started <i class="fas fa-arrow-right ml-2"></i></a>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="row row-1">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3 class="feature-title">Point of Sale</h3>
                        <p class="feature-text">Fast and intuitive sales processing with barcode scanning, product search, and multiple payment methods. Designed for speed and ease of use.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h3 class="feature-title">Promotions Management</h3>
                        <p class="feature-text">Create and manage promotions with specific date ranges, active days, and product-specific discounts to boost your sales.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="feature-title">Reporting & Analytics</h3>
                        <p class="feature-text">Comprehensive reports and analytics to track sales, inventory, and customer behavior. Make data-driven decisions for your business.</p>
                    </div>
                </div>
            </div>
            
            <div class="row row-2 mt-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <h3 class="feature-title">Inventory Management</h3>
                        <p class="feature-text">Keep track of your stock in real-time. Receive alerts for low stock items and manage product categories efficiently.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="feature-title">Customer Management</h3>
                        <p class="feature-text">Build and maintain customer relationships with detailed profiles, purchase history, and loyalty programs.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h3 class="feature-title">Document Management</h3>
                        <p class="feature-text">Generate and manage invoices, receipts, orders, quotes, credit notes, and delivery notes all in one place.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- About the System Section -->
    <section class="about-section" id="about">
        <div class="container">
            <div class="section-header text-center">
                <h2 class="section-title">Why Choose <?php echo SITE_NAME; ?>?</h2>
                <p class="section-subtitle">A comprehensive business management solution designed for modern enterprises</p>
            </div>
            
            <div class="row mt-5">
                <div class="col-lg-6">
                    <div class="about-content">
                        <h3>Comprehensive Business Management</h3>
                        <p>MTECH UGANDA provides a complete suite of tools to manage every aspect of your business operations, from inventory and sales to customer management and reporting.</p>
                        
                        <ul class="feature-list">
                            <li><i class="fas fa-check-circle"></i> <span>Real-time inventory tracking with low stock alerts</span></li>
                            <li><i class="fas fa-check-circle"></i> <span>Point of sale system with barcode scanning</span></li>
                            <li><i class="fas fa-check-circle"></i> <span>Customer relationship management with purchase history</span></li>
                            <li><i class="fas fa-check-circle"></i> <span>Comprehensive financial reporting and analytics</span></li>
                            <li><i class="fas fa-check-circle"></i> <span>Supplier management and automated ordering</span></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="system-highlight">
                        <h3>Key System Features</h3>
                        
                        <div class="highlight-item">
                            <div class="highlight-icon"><i class="fas fa-tags"></i></div>
                            <div class="highlight-content">
                                <h4>Advanced Promotions Management</h4>
                                <p>Create and manage sophisticated promotions with specific date ranges, active days, and product-specific discounts to boost your sales and customer engagement.</p>
                            </div>
                        </div>
                        
                        <div class="highlight-item">
                            <div class="highlight-icon"><i class="fas fa-file-invoice"></i></div>
                            <div class="highlight-content">
                                <h4>Comprehensive Document Management</h4>
                                <p>Generate and manage all your business documents including invoices, receipts, purchase orders, quotes, credit notes, and delivery notes in one centralized system.</p>
                            </div>
                        </div>
                        
                        <div class="highlight-item">
                            <div class="highlight-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="highlight-content">
                                <h4>Enhanced Security Features</h4>
                                <p>Secure login process with real-time verification status and logout confirmation with countdown timer to prevent accidental logouts and protect your business data.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-12">
                    <div class="system-benefits">
                        <h3 class="text-center mb-4">Business Benefits</h3>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="benefit-item">
                                    <div class="benefit-icon"><i class="fas fa-chart-pie"></i></div>
                                    <h4>Increased Efficiency</h4>
                                    <p>Streamline your operations with automated workflows and integrated systems that eliminate duplicate data entry and reduce manual errors.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="benefit-item">
                                    <div class="benefit-icon"><i class="fas fa-money-bill-wave"></i></div>
                                    <h4>Cost Reduction</h4>
                                    <p>Optimize inventory levels, reduce waste, and better manage your resources to significantly cut operational costs.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="benefit-item">
                                    <div class="benefit-icon"><i class="fas fa-bullseye"></i></div>
                                    <h4>Data-Driven Decisions</h4>
                                    <p>Make informed business decisions based on real-time data and comprehensive analytics dashboards.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section>
        <div class="container">
            <div class="cta-container">
                <h2>Ready to streamline your business operations?</h2>
                <p class="mb-4">Get started with <?php echo SITE_NAME; ?> today and experience the difference.</p>
                <button class="login-btn" id="loginBtn" onclick="startLogin()">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </div>
        </div>
    </section>
                <a href="login.php" class="btn login-btn btn-lg">Login to Your Account <i class="fas fa-sign-in-alt ml-2"></i></a>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-left mb-3 mb-md-0">
                    <img src="assets/images/logo.svg" alt="Logo" class="footer-logo">
                    <p class="mt-2">© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-right">
                    <div class="footer-links">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Contact Us</a>
                    </div>
                    <p class="version-info">Version 1.2.0</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Animation keyframes -->
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-loaded .hero-section {
            animation: fadeIn 0.8s ease-out;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add class to body after loading to trigger animations
        document.body.classList.add('page-loaded');
        
        // Initialize animation for feature cards
        const featureRows = document.querySelectorAll('.row');
        if (featureRows.length >= 2) {
            featureRows[0].classList.add('row-1');
            featureRows[1].classList.add('row-2');
        }
        
        // Create cursor glow element
        const cursorGlow = document.createElement('div');
        cursorGlow.className = 'cursor-glow';
        document.body.appendChild(cursorGlow);
        
        // Create star particles for cursor effect
        function createStarParticle() {
            const star = document.createElement('div');
            star.className = 'star-particle';
            star.style.cssText = `
                position: absolute;
                width: ${Math.random() * 3 + 1}px;
                height: ${Math.random() * 3 + 1}px;
                background-color: rgba(255, 255, 255, ${Math.random() * 0.7 + 0.3});
                border-radius: 50%;
                pointer-events: none;
                z-index: 2;
                opacity: 0;
                transition: opacity 0.3s ease;
                box-shadow: 0 0 ${Math.random() * 5 + 2}px rgba(53, 132, 228, 0.8);
                filter: blur(${Math.random() * 1.5}px);
            `;
            return star;
        }
        
        // Create star particles pool
        const starParticles = [];
        for (let i = 0; i < 15; i++) {
            const star = createStarParticle();
            document.body.appendChild(star);
            starParticles.push(star);
        }
        
        // Cursor glow effect
        const aboutSection = document.querySelector('.about-section');
        if (aboutSection) {
            // Add hover-active class to enable hover effects
            aboutSection.classList.add('hover-active');
            
            // Show cursor glow only when mouse enters the about section
            aboutSection.addEventListener('mouseenter', function() {
                cursorGlow.style.opacity = '1';
                
                // Reset transform and filter when entering section
                aboutSection.querySelector('.container').style.transition = 'transform 0.6s ease, filter 0.6s ease';
            });
            
            aboutSection.addEventListener('mouseleave', function() {
                cursorGlow.style.opacity = '0';
                
                // Reset star particles
                starParticles.forEach(star => {
                    star.style.opacity = '0';
                });
                
                // Reset container transform and filter when leaving section
                aboutSection.querySelector('.container').style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
                aboutSection.querySelector('.container').style.filter = 'brightness(1)';
            });
            
            // Follow mouse cursor with glow effect
            aboutSection.addEventListener('mousemove', function(e) {
                // Get section position to calculate relative position
                const rect = aboutSection.getBoundingClientRect();
                
                // Calculate cursor position relative to about section
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top + window.scrollY;
                
                // Position the glow effect center on cursor
                cursorGlow.style.left = (x - 125) + 'px';
                cursorGlow.style.top = (y - 125) + 'px';
                
                // Animate star particles around cursor
                starParticles.forEach((star, index) => {
                    // Only show stars inside the about section
                    if (y > 0 && y < rect.height) {
                        // Random position around cursor
                        const angle = Math.random() * Math.PI * 2;
                        const distance = Math.random() * 120 + 50;
                        const starX = x + Math.cos(angle) * distance;
                        const starY = y + Math.sin(angle) * distance;
                        
                        // Set position with slight delay based on index
                        setTimeout(() => {
                            star.style.left = (starX) + 'px';
                            star.style.top = (starY) + 'px';
                            star.style.opacity = Math.random() * 0.8 + 0.2;
                            
                            // Fade out after random time
                            setTimeout(() => {
                                star.style.opacity = '0';
                            }, Math.random() * 1000 + 500);
                        }, index * 50);
                    }
                });
                
                // Create a slight movement effect for nearby stars by adjusting container perspective
                const xPercent = (x / rect.width) * 100;
                const yPercent = (y / rect.height) * 100;
                
                // Subtle transform effect on container to enhance 3D feeling
                aboutSection.querySelector('.container').style.transform = 
                    `perspective(1000px) rotateX(${(yPercent - 50) * 0.03}deg) rotateY(${(50 - xPercent) * 0.03}deg)`;
                
                // Make stars appear to react to cursor by adjusting their opacity and blur
                // This creates a subtle interaction effect with the star layers
                const intensity = Math.min(1, Math.max(0.2, (x + y) / (rect.width + rect.height)));
                aboutSection.querySelector('.container').style.filter = `brightness(${1 + intensity * 0.15})`;
            });
        }
    });
    
    function startLogin() {
        const overlay = document.getElementById('loadingOverlay');
        const status = document.getElementById('loadingStatus');
        const progressBar = document.getElementById('progressBar');
        const btn = document.getElementById('loginBtn');
        
        // Apply loading state to button
        btn.classList.add('loading');
        btn.innerHTML = '<span>Logging in</span> <div class="spinner-border" role="status" aria-hidden="true"></div>';
        
        // Show the overlay with animation
        overlay.classList.add('active');
        
        // Define sequence of loading messages with more detail
        let messages = [
            'Connecting to database...',
            'Authenticating credentials...',
            'Preparing your dashboard...',
            'Almost there...',
            'Redirecting to login page...'
        ];
        
        // Progress percentages for each step
        let progress = [20, 40, 60, 80, 100];
        
        let idx = 0;
        status.textContent = messages[idx];
        progressBar.style.width = progress[idx] + '%';
        
        // Set interval to update messages and progress bar
        let interval = setInterval(() => {
            idx++;
            if (idx < messages.length) {
                // Update status text with fade effect
                status.style.opacity = 0;
                setTimeout(() => {
                    status.textContent = messages[idx];
                    status.style.opacity = 1;
                }, 300);
                
                // Update progress bar
                progressBar.style.width = progress[idx] + '%';
            } else {
                // Final step - redirect to login page
                clearInterval(interval);
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 500);
            }
        }, 1000);
    }
    </script>
</body>
</html>
