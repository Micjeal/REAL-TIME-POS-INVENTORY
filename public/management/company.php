<?php
// Start the session
session_start();

// Include database configuration
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get company data
$company = [];
try {
    $db = get_db_connection();
    $stmt = $db->query("SELECT * FROM company LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = get_db_connection();
        
        // Handle logo upload
        $logo_path = $company['logo_path'] ?? '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/company/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $new_filename = 'company_logo_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $logo_path = 'assets/images/company/' . $new_filename;
            }
        }

        // Update or insert company data
        if ($company) {
            $sql = "UPDATE company SET 
                    name = ?, tax_number = ?, street_name = ?, building_number = ?,
                    additional_street_name = ?, plot_identification = ?, district = ?,
                    postal_code = ?, city = ?, state_province = ?, country = ?,
                    phone_number = ?, email = ?, bank_account = ?, bank_acc_number = ?,
                    bank_details = ?, logo_path = ? WHERE id = ?"; 
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $_POST['name'], $_POST['tax_number'], $_POST['street_name'],
                $_POST['building_number'], $_POST['additional_street_name'],
                $_POST['plot_identification'], $_POST['district'], $_POST['postal_code'],
                $_POST['city'], $_POST['state_province'], $_POST['country'],
                $_POST['phone_number'], $_POST['email'], $_POST['bank_account'],
                $_POST['bank_acc_number'], $_POST['bank_details'], $logo_path,
                $company['id']
            ]);
        } else {
            $sql = "INSERT INTO company (
                    name, tax_number, street_name, building_number, additional_street_name,
                    plot_identification, district, postal_code, city, state_province,
                    country, phone_number, email, bank_account, bank_acc_number,
                    bank_details, logo_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; 
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $_POST['name'], $_POST['tax_number'], $_POST['street_name'],
                $_POST['building_number'], $_POST['additional_street_name'],
                $_POST['plot_identification'], $_POST['district'], $_POST['postal_code'],
                $_POST['city'], $_POST['state_province'], $_POST['country'],
                $_POST['phone_number'], $_POST['email'], $_POST['bank_account'],
                $_POST['bank_acc_number'], $_POST['bank_details'], $logo_path
            ]);
        }
        
        // Refresh company data
        $stmt = $db->query("SELECT * FROM company LIMIT 1");
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        $success = "Company information updated successfully!";
        
    } catch (PDOException $e) {
        $error = "Error updating company information: " . $e->getMessage();
    }
}

$page_title = "Company Settings";
require_once '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Company Information</h4>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Company Name</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($company['name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Tax Number</label>
                                    <input type="text" name="tax_number" class="form-control" value="<?php echo htmlspecialchars($company['tax_number'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Street Name</label>
                                    <input type="text" name="street_name" class="form-control" value="<?php echo htmlspecialchars($company['street_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Building Number</label>
                                    <input type="text" name="building_number" class="form-control" value="<?php echo htmlspecialchars($company['building_number'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Additional Street Name</label>
                                    <input type="text" name="additional_street_name" class="form-control" value="<?php echo htmlspecialchars($company['additional_street_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Plot Identification</label>
                                    <input type="text" name="plot_identification" class="form-control" value="<?php echo htmlspecialchars($company['plot_identification'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>District</label>
                                    <input type="text" name="district" class="form-control" value="<?php echo htmlspecialchars($company['district'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Postal Code</label>
                                    <input type="text" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($company['postal_code'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>State/Province</label>
                                    <input type="text" name="state_province" class="form-control" value="<?php echo htmlspecialchars($company['state_province'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($company['country'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="tel" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($company['phone_number'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Bank Account</label>
                                    <input type="text" name="bank_account" class="form-control" value="<?php echo htmlspecialchars($company['bank_account'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Bank Account Number</label>
                                    <input type="text" name="bank_acc_number" class="form-control" value="<?php echo htmlspecialchars($company['bank_acc_number'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Bank Details</label>
                                    <textarea name="bank_details" class="form-control" rows="3"><?php echo htmlspecialchars($company['bank_details'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Company Logo</label>
                                    <?php if (!empty($company['logo_path'])): ?>
                                        <div class="mb-2">
                                            <img src="../<?php echo htmlspecialchars($company['logo_path']); ?>" alt="Company Logo" style="max-width: 200px;" class="img-thumbnail">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="logo" class="form-control-file" accept="image/*">
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>