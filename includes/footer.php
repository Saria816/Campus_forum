    </main>
    
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5>Campus Forum</h5>
                    <p class="text-muted">A place for students to connect, share ideas, and build community.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-decoration-none text-light">Home</a></li>
                        <li><a href="forum.php" class="text-decoration-none text-light">Forum</a></li>
                        <?php if (isLoggedIn()): ?>
                        <li><a href="chat.php" class="text-decoration-none text-light">Chat</a></li>
                        <li><a href="ai_chat.php" class="text-decoration-none text-light">AI Chat</a></li>
                        <li><a href="staff_message.php" class="text-decoration-none text-light">Contact Staff</a></li>
                        <?php else: ?>
                        <li><a href="login.php" class="text-decoration-none text-light">Login</a></li>
                        <li><a href="register.php" class="text-decoration-none text-light">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <address class="text-muted">
                        <i class="fas fa-map-marker-alt me-2"></i> 123 University Ave<br>
                        <i class="fas fa-phone me-2"></i> (123) 456-7890<br>
                        <i class="fas fa-envelope me-2"></i> <a href="mailto:info@campusforum.com" class="text-decoration-none text-light">info@campusforum.com</a>
                    </address>
                    <div class="mt-2">
                        <a href="#" class="text-light me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-light me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Campus Forum. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-decoration-none text-light me-3">Privacy Policy</a>
                    <a href="#" class="text-decoration-none text-light me-3">Terms of Service</a>
                    <a href="#" class="text-decoration-none text-light">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- jQuery - 使用国内CDN -->
    <script src="https://cdn.staticfile.org/jquery/3.6.0/jquery.min.js"></script>
    <!-- Bootstrap JS - 使用国内CDN -->
    <script src="https://cdn.staticfile.org/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    
    <!-- Check for updates script -->
    <?php if (isLoggedIn()): ?>
    <script>
    // 定期检查新消息和通知
    function checkUpdates() {
        fetch('check_updates.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新通知计数
                    const notificationBadge = document.querySelector('.nav-link[href="notifications.php"] .badge');
                    if (notificationBadge) {
                        if (data.unread_notifications > 0) {
                            notificationBadge.textContent = data.unread_notifications;
                            notificationBadge.style.display = 'inline';
                        } else {
                            notificationBadge.style.display = 'none';
                        }
                    }
                    
                    // 更新消息计数
                    const messageBadge = document.querySelector('.nav-link[href="chat.php"] .badge');
                    if (messageBadge) {
                        if (data.unread_messages > 0) {
                            messageBadge.textContent = data.unread_messages;
                            messageBadge.style.display = 'inline';
                        } else {
                            messageBadge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => console.error('Error checking updates:', error));
    }
    
    // 页面加载后立即检查一次
    document.addEventListener('DOMContentLoaded', function() {
        checkUpdates();
        
        // 然后每30秒检查一次
        setInterval(checkUpdates, 30000);
    });
    </script>
    <?php endif; ?>
</body>
</html> 