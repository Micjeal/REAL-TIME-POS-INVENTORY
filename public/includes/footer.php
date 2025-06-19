<?php if (!defined('INCLUDED')) exit(); ?>
            </div><!-- /.container-fluid -->
        </div><!-- /.main-content -->
    </div><!-- /#app -->

    <!-- jQuery 3.5.1 (required for Bootstrap 4) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Popper.js (required for Bootstrap dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap 4.5.2 JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- DataTables JS (compatible with Bootstrap 4) -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Global variables
        const SITE_URL = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/');
        
        $(document).ready(function() {
            // Toggle sidebar
            $('#toggleSidebar, #mobileMenuToggle').on('click', function() {
                $('.sidebar').toggleClass('collapsed');
                $('.sidebar-overlay').toggleClass('show');
            });
            
            // Close sidebar when clicking overlay
            $('.sidebar-overlay').on('click', function() {
                $('.sidebar').addClass('collapsed');
                $(this).removeClass('show');
            });
            
            // Update date and time
            function updateDateTime() {
                const now = new Date();
                const options = { 
                    weekday: 'short', 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                };
                const dateTimeStr = now.toLocaleDateString('en-US', options);
                $('#currentDateTime').text(dateTimeStr);
            }
            
            // Update time every minute
            updateDateTime();
            setInterval(updateDateTime, 60000);
            
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Initialize popovers
            $('[data-toggle="popover"]').popover();
        });
    </script>
    
    <!-- User Dropdown Script (must be after jQuery and Bootstrap) -->
    <script src="js/user-dropdown.js"></script>
</body>
</html>
