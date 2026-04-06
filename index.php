<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loom - State of the Art HR & CRM SaaS</title>
    <meta name="description" content="The ultimate Multi-Tenant HRMS and Lead Management SaaS for modern agencies and enterprises.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Using our new standalone premium landing page styling with cache busting -->
    <link rel="stylesheet" href="assets/css/landing.css?v=2.2">
    <script>
        // Simple script for navbar scroll effect
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('nav');
            if(window.scrollY > 50) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        });
    </script>
</head>
<body>
    <!-- Animated Glowing Background Orbs -->
    <div class="bg-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <!-- Navigation -->
    <nav>
        <div class="container nav-container">
            <a href="/" class="logo">Loom</a>
            <ul class="nav-links">
                <li><a href="#features">Platform Features</a></li>
                <li><a href="how-it-works.php">How It Works</a></li>
            </ul>
            <div class="nav-actions">
                <a href="login.php" class="btn btn-outline">Sign In</a>
                <a href="#contact" class="btn btn-primary">Request Access</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Empower Your Enterprise with <br><class><span class="text-gradient">Intelligent HR & CRM</span></h1>
                <p>An elite, unified platform designed to manage your workforce, track attendance via GPS, close CRM leads faster, and oversee multiple franchise branches with pinpoint accuracy.</p>
                <div class="hero-actions">
                    <a href="#contact" class="btn btn-primary">Start Free Concierge Setup</a>
                    <a href="#features" class="btn btn-outline">Explore Features</a>
                </div>
            </div>

            <!-- Stylized Abstract UI Preview -->
            <div class="preview-container">
                <div class="preview-img glass-panel">
                    <div class="preview-ui">
                        <div class="ui-sidebar">
                            <div style="padding: 20px;">
                                <div class="ui-line short" style="background:var(--primary);"></div>
                                <div class="ui-line"></div>
                                <div class="ui-line"></div>
                                <div class="ui-line"></div>
                            </div>
                        </div>
                        <div class="ui-main">
                            <div class="ui-line short"></div>
                            <div class="ui-cards">
                                <div class="ui-card"></div>
                                <div class="ui-card" style="background:rgba(16, 185, 129, 0.1); border-color:rgba(16, 185, 129, 0.2);"></div>
                                <div class="ui-card" style="background:rgba(244, 114, 182, 0.1); border-color:rgba(244, 114, 182, 0.2);"></div>
                            </div>
                            <div class="ui-line" style="height: 150px; border-radius:12px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Features Section -->
    <section id="features" class="features container">
        <div class="section-title">
            <h2>Architected for Scale</h2>
            <p style="color:var(--text-muted); font-size:1.15rem; max-width:600px; margin:0 auto;">Unleash the full potential of your agency with bespoke tools tailored exactly to your operation's flow.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card glass-panel">
                <div class="f-icon">🏢</div>
                <h3>Multi-Branch Mastery</h3>
                <p>Command your offline and online presence. Create headquarters and infinite sub-branches, assigning specific managers and tracking revenue streams instantly.</p>
            </div>
            <div class="feature-card glass-panel">
                <div class="f-icon">👥</div>
                <h3>Advanced HRMS & Geo-Tracking</h3>
                <p>Deploy true accountability. Employees clock in via GPS verified selfies. Approve leaves, manage complex shifts, and generate payroll-ready data.</p>
            </div>
            <div class="feature-card glass-panel">
                <div class="f-icon">📈</div>
                <h3>State-of-the-Art CRM</h3>
                <p>Never drop a lead. Utilize a visual Kanban board, track full interaction history, assign actionable tasks, and monitor sales person conversions in real-time.</p>
            </div>
            <div class="feature-card glass-panel">
                <div class="f-icon">💰</div>
                <h3>Finance & Commission Engine</h3>
                <p>Fully integrated expense approvals and revenue logging. Calculate sub-branch cuts, track payouts, and visualize net margins dynamically.</p>
            </div>
        </div>
    </section>

    <!-- Setup / CTA Section -->
    <section id="contact" class="cta-section">
        <div class="container">
            <div class="cta-grid">
                <div>
                    <h2 style="font-size: 3rem; font-weight: 800; line-height: 1.1; margin-bottom: 1.5rem;">Ready to upgrade your workflow?</h2>
                    <p style="font-size: 1.15rem; color: var(--text-muted); margin-bottom: 2rem; line-height: 1.8;">
                        Experience Loom with no upfront commitment. Submit your request and our concierge team will manually verify your business and provision a master dashboard. 
                        <strong>We handle the heavy lifting.</strong>
                    </p>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:1rem;">
                        <li style="display:flex; align-items:center; gap:10px; color:#e2e8f0;"><span style="color:var(--success)">✔</span> Instant Sandbox Access</li>
                        <li style="display:flex; align-items:center; gap:10px; color:#e2e8f0;"><span style="color:var(--success)">✔</span> Dedicated Account Manager</li>
                        <li style="display:flex; align-items:center; gap:10px; color:#e2e8f0;"><span style="color:var(--success)">✔</span> Data Migration Support</li>
                    </ul>
                </div>

                <div class="glass-panel demo-form">
                    <h3 style="font-size: 1.5rem; margin-bottom: 2rem;">Request Concierge Setup</h3>
                    
                    <?php if (isset($_SESSION['demo_success'])): ?>
                        <div class="flash success">
                            <?php echo $_SESSION['demo_success']; unset($_SESSION['demo_success']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['demo_error'])): ?>
                        <div class="flash error">
                            <?php echo $_SESSION['demo_error']; unset($_SESSION['demo_error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="submit_demo.php" method="POST">
                        <div style="display:flex; gap:1.5rem;">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label" for="name">Full Name *</label>
                                <input type="text" id="name" name="name" class="form-control" required placeholder="John Doe">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label" for="company_name">Company Name *</label>
                                <input type="text" id="company_name" name="company_name" class="form-control" required placeholder="Acme Corp">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">Work Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required placeholder="john@acmecorp.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="+1 (555) 000-0000">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; padding:1.2rem; font-size:1.1rem; margin-top:1rem; border-radius:12px;">
                            Secure My Access →
                        </button>
                        <p style="text-align:center; font-size:0.8rem; color:var(--text-muted); margin-top:1.5rem;">By submitting, you agree to our Terms of Service & Privacy Policy.</p>
                    </form>
                </div>
            </div>
        </div>
    </section>

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
                        <li><a href="#features">Features</a></li>
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
