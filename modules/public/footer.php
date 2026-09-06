<footer class="site-footer">
    <div class="footer-wave">
        <svg viewBox="0 0 1440 50" preserveAspectRatio="none">
            <path d="M0,25 C360,50 720,0 1080,25 C1260,37.5 1350,30 1440,25 L1440,50 L0,50 Z" fill="#102a54" />
            <path d="M0,35 C240,15 480,45 720,25 C960,5 1200,35 1440,15 L1440,50 L0,50 Z"
                fill="rgba(21,145,220,0.06)" />
        </svg>
    </div>
    <div class="footer-main">
        <div class="container">
            <div class="row g-3">
                <div class="col-6 col-lg-4">
                    <div class="footer-brand">JDM Kenya</div>
                    <p class="footer-tagline">Building a discipleship movement with faith, clarity, and service.</p>
                    <div class="footer-social">
                        <a href="https://www.facebook.com/share/1DuVRA4Qph/" target="_blank" title="Facebook"
                            aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" target="_blank" title="Instagram" aria-label="Instagram"><i
                                class="bi bi-instagram"></i></a>
                        <a href="https://vm.tiktok.com/ZS9jGEtHaDFBC-dncuF/" target="_blank" title="TikTok"
                            aria-label="TikTok"><i class="bi bi-tiktok"></i></a>
                        <a href="#" target="_blank" title="YouTube" aria-label="YouTube"><i
                                class="bi bi-youtube"></i></a>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="<?= BASE_PATH ?>/index.php">Home</a></li>
                        <li><a href="<?= BASE_PATH ?>/about.php">About Us</a></li>
                        <li><a href="<?= BASE_PATH ?>/activities.php">Events</a></li>
                        <li><a href="<?= BASE_PATH ?>/contact.php">Contact</a></li>
                        <li><a href="<?= BASE_PATH ?>/resources.php">Resources</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-3">
                    <h5>Explore JDM</h5>
                    <ul>
                        <li><a href="<?= BASE_PATH ?>/sports.php">Sports Ministry</a></li>
                        <li><a href="<?= BASE_PATH ?>/prayer_wall.php">Prayer Wall</a></li>
                        <li><a href="<?= BASE_PATH ?>/about_ministry.php">Our Mission</a></li>
                        <li><a href="<?= BASE_PATH ?>/activities.php">Events</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-3">
                    <h5>Get In Touch</h5>
                    <div class="footer-contact">
                        <p><i class="bi bi-geo-alt"></i> Nairobi, Kenya</p>
                        <p><i class="bi bi-envelope"></i> Email: jesusdisciplemovementk@gmail.com</p>
                        <p><i class="bi bi-phone"></i> 0792 697 855</p>
                        <p><i class="bi bi-globe"></i> jdmkenya.com</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-6 text-center text-md-start mb-1 mb-md-0">
                    <p>&copy; <?= date('Y') ?> Jesus Disciple Movement of Kenya. All rights reserved.</p>
                </div>
                <div class="col-12 col-md-6 text-center text-md-end footer-links">
                    <a href="<?= BASE_PATH ?>/privacy.php">Privacy Policy</a>
                    <a href="<?= BASE_PATH ?>/terms.php">Terms of Service</a>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-developer">
        <div class="container text-center">
            <p>Developed by <a href="https://kennedycheroben.co.ke" target="_blank" rel="noopener noreferrer"><i class="bi bi-code-slash"></i> Kennedy Cheroben</a></p>
        </div>
    </div>
</footer>

<?php if (!isset($as_no_wrapper) || !$as_no_wrapper): ?>
</div>
</div>
<?php endif; ?>

<?php if (empty($skipAnimationScripts)): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/lenis.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/animated-scroll.js?v=<?= filemtime(dirname(dirname(__DIR__)) . '/assets/js/animated-scroll.js') ?>"></script>
<?php endif; ?>
