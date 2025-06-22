<!-- Sidebar -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse" style="display: flex; flex-direction: column; height: 100vh;">
    <div class="position-sticky pt-3" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
        <div class="text-center mb-4">
            <h4 class="text-white">MTECH UGANDA</h4>
            <p class="text-white-50 mb-0">Management System</p>
        </div>
        
        <div style="overflow-y: auto; overflow-x: hidden; flex: 1; padding-bottom: 80px;">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <!-- Sales Section -->
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>SALES</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-fw fa-shopping-cart me-2"></i>
                    New Sale
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-fw fa-list me-2"></i>
                    Sales List
                </a>
            </li>
            
            <!-- Inventory Section -->
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>INVENTORY</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-fw fa-boxes me-2"></i>
                    Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-fw fa-box-open me-2"></i>
                    Stock Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-fw fa-tags me-2"></i>
                    Categories
                </a>
            </li>
            
            <!-- Customers Section -->
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>CUSTOMERS</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i class="fas fa-fw fa-users me-2"></i>
                    Manage Customers
                </a>
            </li>
            
            <!-- Reports Section -->
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>REPORTS</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-fw fa-chart-bar me-2"></i>
                    Sales Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-fw fa-chart-pie me-2"></i>
                    Inventory Reports
                </a>
            </li>
            
            <!-- Administration Section -->
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>ADMINISTRATION</span>
                </h6>
            </li>
            <?php if (strtolower($user_role) === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-fw fa-user-cog me-2"></i>
                    User Management
                </a>
            </li>
            <?php endif; ?>
            <?php if (in_array(strtolower($user_role), ['admin', 'manager'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : ''; ?>" href="feedback.php">
                    <i class="fas fa-fw fa-comments me-2"></i>
                    Feedback
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-fw fa-cog me-2"></i>
                    Settings
                </a>
            </li>
            <?php endif; ?>
        </ul>
        </div>
        
        <div class="w-100 p-3 border-top border-secondary" style="background-color: var(--bs-dark); position: sticky; bottom: 0; z-index: 1000;">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_fullname); ?>" alt="User" class="rounded-circle me-2" width="32" height="32">
                    <strong><?php echo htmlspecialchars($user_fullname); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sign out</a></li>
                </ul>
            </div>
        </div>
    </div>
    <style>
    /* Custom scrollbar for sidebar */
    #sidebar::-webkit-scrollbar {
        width: 6px;
    }
    #sidebar::-webkit-scrollbar-track {
        background: #2c3034;
    }
    #sidebar::-webkit-scrollbar-thumb {
        background: #4e555b;
        border-radius: 3px;
    }
    #sidebar::-webkit-scrollbar-thumb:hover {
        background: #5a6268;
    }
    </style>
</nav>
