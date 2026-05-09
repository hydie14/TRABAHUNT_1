<?php include '../DATABASE/db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About PESO - Public Employment Service Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="landing-page.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="new_landing_page.php" class="logo">
                <img src="../BONGABON.png" style="width: 70px;" alt="PESO Logo">
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="new_landing_page.php">Home</a></li>
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

    <main style="min-height: calc(100vh - 150px);">
        <section class="features" id="about" style="background-color: #ffffff; padding-top: 2rem;">
            <div class="features-content">
                <div class="section-title">
                    <h2>About PESO Bongabon</h2>
                    <p>Our Mission and Vision</p>
                </div>
                <div class="mission-vision-grid">
                    <div class="feature-card">
                        <h3>Our Mission</h3>
                        <p>As a non-fee charging facilitation agency, it aims to strengthen the overall labor exchange system to address skills, employment and other related concerns.</p>
                    </div>
                    <div class="feature-card">
                        <h3>Our Vision</h3>
                        <p>The PESO is an excellent multi-service facility created pursuant to R.A 8759 to ensure responsive and efficient delivery of employment services leading to higher labor market outcomes.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-bottom">
            <p>&copy; 2026 Public Employment Service Office | Bongabon, Nueva Ecija. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>