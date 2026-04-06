<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Loom</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
    <style>
        .legal-content h2 { font-size: 1.8rem; margin-top: 2.5rem; margin-bottom: 1rem; color: #fff; }
        .legal-content p, .legal-content li { color: var(--text-muted); line-height: 1.7; font-size: 1.05rem; margin-bottom: 1rem; }
        .legal-content ul { margin-left: 1.5rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="bg-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <!-- Navigation -->
    <nav class="scrolled">
        <div class="container nav-container">
            <a href="index.php" class="logo">Loom</a>
            <ul class="nav-links">
                <li><a href="index.php#features">Platform Features</a></li>
                <li><a href="how-it-works.php">How It Works</a></li>
            </ul>
            <div style="display: flex; gap: 1rem;">
                <a href="login.php" class="btn btn-outline">Sign In</a>
                <a href="index.php#contact" class="btn btn-primary">Request Access</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container" style="padding-top: 150px; padding-bottom: 100px; min-height: 80vh;">
        <div style="max-width: 800px; margin: 0 auto; margin-bottom: 3rem;">
            <h1 style="font-size: 3rem; font-weight: 800; margin-bottom: 1rem;"><span class="text-gradient">Privacy Policy</span></h1>
            <p style="color: var(--text-muted);">Last Updated: <?= date('F d, Y') ?></p>
        </div>

        <div class="glass-panel legal-content" style="max-width: 800px; margin: 0 auto; padding: 3rem;">
            <h2>1. Information We Collect</h2>
            <p>We collect information that you directly provide us when utilizing Loom. This includes:</p>
            <ul>
                <li>Account and profile information (Name, Email, Phone Number).</li>
                <li>Geolocation data securely collected during employee Clock-In and Check-In processes.</li>
                <li>Biometric photographic data utilized strictly for attendance verification.</li>
                <li>Financial and transactional data associated with franchise commissions and expenses.</li>
            </ul>

            <h2>2. How We Use Your Data</h2>
            <p>Your data is processed in a multi-tenant, isolated database environment. We use this data to:</p>
            <ul>
                <li>Provide, maintain, and improve the Loom platform.</li>
                <li>Process attendance, leaves, and CRM lead movements for your organization.</li>
                <li>Calculate and display analytics in your Super Admin dashboard.</li>
                <li>Send critical technical notices and security alerts.</li>
            </ul>

            <h2>3. Secure Data Isolation</h2>
            <p>Because Loom serves multiple independent franchises and headquarters, we enforce strict Company ID isolation at the database layer. Cross-tenant data bleeds are mathematically impossible within our query structures.</p>

            <h2>4. Third-Party Sharing</h2>
            <p>We do not sell, rent, or lease your organizational data to third parties. Data is only shared with trusted cloud hosting providers specifically contracted to maintain the infrastructure of Loom.</p>

            <h2>5. Contact Us</h2>
            <p>If you have questions regarding this Privacy Policy or your data, please contact us at <a href="mailto:support@loom.com" style="color:var(--primary);">support@loom.com</a>.</p>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-logo">Loom</div>
                    <p style="color:var(--text-muted); line-height:1.6; max-width:300px;">The premier administrative layer for managing scalable organizations across the globe.</p>
                </div>
                <div>
                    <h4>Platform</h4>
                    <ul class="footer-links">
                        <li><a href="index.php#features">Features</a></li>
                        <li><a href="how-it-works.php">How it Works</a></li>
                        <li><a href="login.php">Partner Login</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Legal & Contact</h4>
                    <ul class="footer-links">
                        <li><a href="privacy.php" style="color:#fff;">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                        <li><a href="mailto:support@loom.com">support@loom.com</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; <?= date('Y') ?> Loom Technologies. Built for Excellence.
            </div>
        </div>
    </footer>
</body>
</html>
