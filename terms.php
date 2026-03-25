<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - DRHrms</title>
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
            <a href="index.php" class="logo">DR<span>Hrms</span></a>
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
            <h1 style="font-size: 3rem; font-weight: 800; margin-bottom: 1rem;"><span class="text-gradient">Terms of Service</span></h1>
            <p style="color: var(--text-muted);">Last Updated: <?= date('F d, Y') ?></p>
        </div>

        <div class="glass-panel legal-content" style="max-width: 800px; margin: 0 auto; padding: 3rem;">
            <h2>1. Acceptance of Terms</h2>
            <p>By accessing and using DRHrms ("the Platform"), you accept and agree to be bound by the terms and provisions of this agreement. If you do not agree to abide by these terms, you are not authorized to use or access the Platform.</p>

            <h2>2. Platform Access and Security</h2>
            <p>You are responsible for maintaining the confidentiality of your master credentials and passwords. You agree to accept responsibility for all activities that occur under your account, including activities conducted by sub-branches created under your hierarchy.</p>

            <h2>3. Multi-Tenant Infrastructure</h2>
            <p>The Platform provides SaaS capabilities that allow users to generate downstream tenants (sub-branches). You retain ownership of all data inputted into your tenant instance; however, you grant DRHrms permission to host, backup, and structure that data.</p>

            <h2>4. Acceptable Use Policy</h2>
            <p>You must not use the Platform to:</p>
            <ul>
                <li>Distribute malicious software or engage in phishing.</li>
                <li>Reverse-engineer or bypass security isolations between tenants.</li>
                <li>Submit illicit or unauthorized content into the CRM tracking modules.</li>
            </ul>

            <h2>5. Limitation of Liability</h2>
            <p>DRHrms shall not be liable for any indirect, incidental, special, or consequential damages resulting from the use or inability to use the Platform, including but not limited to damages for loss of profits, data, or other tangibles.</p>

            <h2>6. Termination</h2>
            <p>We reserve the right to suspend or terminate your access to the Platform immediately if we determine that you have breached these Terms, without prior notice or liability.</p>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-logo">DR<span>Hrms</span></div>
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
                        <li><a href="terms.php" style="color:#fff;">Terms of Service</a></li>
                        <li><a href="mailto:support@drhrms.com">support@drhrms.com</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; <?= date('Y') ?> DRHrms Technologies. Built for Excellence.
            </div>
        </div>
    </footer>
</body>
</html>
