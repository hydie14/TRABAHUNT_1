<?php include '../DATABASE/db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PESO - Public Employment Service Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="landing-page.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .partnership {
            padding: 4rem 1.5rem 2rem;
            background: #f9fafb;
        }
        .slider .list .item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 20px;
        }
        @media (max-width: 768px) {
            .slider .list .item img {
                padding: 10px; /* Reduce padding on mobile so logos aren't squished */
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="#" class="logo">
                <img src="../BONGABON.png" style="width: 70px;" alt="PESO Logo">
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="about.php">About</a></li>
                    <li><a href="../LOGIN SIGNUP/new_login.php" class="btn btn-yellow">Login</a></li>
                </ul>
            </nav>
            <button class="menu-toggle" onclick="document.querySelector('.nav-menu').classList.toggle('active')">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <section class="hero" id="home">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Find Your <span class="highlight">Dream Job</span> Today</h1>
                <p>Connect with opportunities, grow your career, and find the perfect match. <br>PESO brings together job seekers and employers in one platform.</p>
                <div class="hero-buttons">
                    <a href="../LOGIN SIGNUP/new_signup.php" class="btn btn-yellow"><b>+ </b>Create Account Now</a>
                </div>
            </div>
        </div>
    </section>

    <main>
    <section class="partnership">
        <div class="features-content">
            <div class="section-title" style="margin-bottom: 2rem;">
                <h2>Our Partner Agencies</h2>
                <p>Collaborating for better employment opportunities</p>
            </div>
        </div>
        <div class="slider" reverse="true" style="--quantity: 6;">
        <div class="list">
            <div class="item" style="--position: 1"><img src="DOLE LOGO.png" alt="DOLE "></div>
            <div class="item" style="--position: 2"><img src="DTI LOGO.png" alt="DTI"></div>
            <div class="item" style="--position: 3"><img src="TESDA LOGO.png" alt="TESDA"></div>
            <div class="item" style="--position: 4"><img src="DMW LOGO.png" alt="DMW"></div>
            <div class="item" style="--position: 5"><img src="OWWA LOGO.png" alt="OWWA"></div>
            <div class="item" style="--position: 6"><img src="POEA LOGO.png" alt="POEA"></div>
           
        </div>
    </div>
    </section>
    </main>

    <section class="features" id="jobs">
        <div class="features-content">
            <div class="section-title">
                <h2>Everything You Need</h2>
                <p>One platform for all your employment needs</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">👤</div>
                    <h3>Job Seekers</h3>
                    <p>Create your profile, upload your resume, and apply to jobs that match your skills.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🏢</div>
                    <h3>Employers</h3>
                    <p>Post jobs and build your dream team efficiently.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h3>Instant Alerts</h3>
                    <p>Get notified about new opportunities that match your profile.</p>
                </div>
            </div>
        </div>
    </section>

 
    <footer class="footer">
        <div class="footer-bottom">
            <p>&copy; 2026 Public Employment Service Office | Bongabon, Nueva Ecija. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
