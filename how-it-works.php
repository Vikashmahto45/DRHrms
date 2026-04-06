<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How It Works - Loom</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
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
                <li><a href="how-it-works.php" style="color:#fff;">How It Works</a></li>
            </ul>
            <div style="display: flex; gap: 1rem;">
                <a href="login.php" class="btn btn-outline">Sign In</a>
                <a href="index.php#contact" class="btn btn-primary">Request Access</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container" style="padding-top: 150px; padding-bottom: 100px; min-height: 80vh;">
        <div style="max-width: 800px; margin: 0 auto; text-align: center; margin-bottom: 4rem;">
            <div class="badge-pill">The Process</div>
            <h1 style="font-size: 3.5rem; font-weight: 800; margin-bottom: 1.5rem; line-height: 1.1;">How <span class="text-gradient">Loom</span> Works</h1>
            <p style="font-size: 1.25rem; color: var(--text-muted); line-height: 1.8;">We believe in white-glove onboarding. Instead of leaving you to figure out complex settings alone, our team sets up your master enterprise architecture for you.</p>
        </div>

        <div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 2rem;">
            <div class="glass-panel" style="padding: 2.5rem; border-left: 4px solid var(--primary);">
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #fff;">1. Request Concierge Setup</h3>
                <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.7;">Fill out the demo request form on our homepage. You do not need a credit card. We only ask for your company's basic information to verify your identity and business needs.</p>
            </div>

            <div class="glass-panel" style="padding: 2.5rem; border-left: 4px solid var(--accent-1);">
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #fff;">2. Business Verification</h3>
                <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.7;">Our onboarding specialists will review your application. We will configure your dedicated Master Headquarters dashboard, isolated from all other tenants for maximum security.</p>
            </div>

            <div class="glass-panel" style="padding: 2.5rem; border-left: 4px solid var(--accent-2);">
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #fff;">3. Receive Your Credentials</h3>
                <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.7;">Once approved, you will receive an email containing your secure Super Admin login credentials. You can immediately log in and begin creating sub-branches for your franchise network.</p>
            </div>

            <div class="glass-panel" style="padding: 2.5rem; border-left: 4px solid var(--success);">
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #fff;">4. Scale Your Operation</h3>
                <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.7;">Start adding Admins, Managers, and Sales Persons to your branches. Track GPS attendance, assign CRM leads to your staff, and watch your platform revenue grow through our analytics dashboard.</p>
            </div>
            
            <div style="text-align: center; margin-top: 3rem;">
                <a href="index.php#contact" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">Start Step 1 Now</a>
            </div>
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
                        <li><a href="privacy.php">Privacy Policy</a></li>
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
