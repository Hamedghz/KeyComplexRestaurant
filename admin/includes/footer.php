        <?php
        $analyticsPrivacyPages = [
            'analytics.php',
            'analytics-traffic-sources.php',
            'visitor-analytics.php',
            'analytics-live.php',
            'analytics-geographic.php',
            'analytics-device.php',
            'visitor-logs.php',
            'traffic-sources.php',
            'visitor-path-analytics.php',
            'live-visitors.php',
            'geographic-analytics.php',
            'device-analytics.php',
        ];
        if (in_array(basename((string)($_SERVER['PHP_SELF'] ?? '')), $analyticsPrivacyPages, true)):
        ?>
            <div class="card" style="margin-top:16px;">
                <div class="card-body text-muted">
                    برای حفظ حریم خصوصی، IP کاربران به‌صورت هش‌شده ذخیره می‌شود و اطلاعات حساس فرم‌ها ثبت نمی‌شود.
                </div>
            </div>
        <?php endif; ?>
    </div> <!-- .main-content -->
    
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
        
        // Confirm delete actions
        document.querySelectorAll('[data-confirm]').forEach(element => {
            element.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
