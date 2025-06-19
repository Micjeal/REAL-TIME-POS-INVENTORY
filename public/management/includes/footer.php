                <!-- Footer -->
                <footer class="mt-5 mb-3">
                    <div class="text-center text-muted">
                        <small>&copy; <?php echo date('Y'); ?> MTECH UGANDA. All rights reserved.</small>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5.1.3 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/v/bs5/dt-1.11.5/datatables.min.js"></script>
    
    <script>
        // Function to show alert messages
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Remove any existing alerts
            $('.alert').alert('close');
            
            // Add the new alert
            $('#alert-container').html(alertHtml);
            
            // Auto-remove alert after 5 seconds
            setTimeout(() => {
                $('.alert').alert('close');
            }, 5000);
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Enable sidebar toggle
            document.querySelector('.sidebar-toggler').addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('collapsed');
                document.querySelector('.main-content').classList.toggle('expanded');
            });
        });
    </script>
</body>
</html>
