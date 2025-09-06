<?php
/**
 * Password Validation Functions
 * Provides strong password validation for all password-related operations
 */

class PasswordValidator {
    /**
     * Validate password strength
     * @param string $password
     * @return array ['valid' => bool, 'errors' => array]
     */
    // public static function validate($password) {
    //     $errors = [];

    //     // Minimum length
    //     if (strlen($password) < 8) {
    //         $errors[] = "Password minimal 8 karakter";
    //     }

    //     // Maximum length
    //     if (strlen($password) > 128) {
    //         $errors[] = "Password maksimal 128 karakter";
    //     }

    //     // At least one uppercase letter
    //     if (!preg_match('/[A-Z]/', $password)) {
    //         $errors[] = "Password harus mengandung minimal 1 huruf besar";
    //     }

    //     // At least one lowercase letter
    //     if (!preg_match('/[a-z]/', $password)) {
    //         $errors[] = "Password harus mengandung minimal 1 huruf kecil";
    //     }

    //     // At least one number
    //     if (!preg_match('/[0-9]/', $password)) {
    //         $errors[] = "Password harus mengandung minimal 1 angka";
    //     }

    //     // At least one special character
    //     if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
    //         $errors[] = "Password harus mengandung minimal 1 karakter khusus";
    //     }

    //     // Check for common weak passwords
    //     $weak_passwords = ['password', '123456', '123456789', 'qwerty', 'abc123', 'password123', 'admin', 'letmein'];
    //     if (in_array(strtolower($password), $weak_passwords)) {
    //         $errors[] = "Password terlalu lemah, gunakan kombinasi yang lebih kuat";
    //     }

    //     // Check for sequential characters
    //     if (preg_match('/(.)\1{2,}/', $password)) {
    //         $errors[] = "Password tidak boleh mengandung karakter berulang 3 kali atau lebih";
    //     }

    //     return [
    //         'valid' => empty($errors),
    //         'errors' => $errors
    //     ];
    // }

    /**
     * Get password strength score (0-100)
     * @param string $password
     * @return int
     */
    public static function getStrengthScore($password) {
        $score = 0;
        $length = strlen($password);

        // Length scoring
        if ($length >= 8) $score += 25;
        if ($length >= 12) $score += 15;
        if ($length >= 16) $score += 10;

        // Character variety scoring
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 15;
        if (preg_match('/[0-9]/', $password)) $score += 15;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) $score += 15;

        // Bonus for mixed case
        if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) $score += 5;

        return min(100, $score);
    }

    /**
     * Get strength label based on score
     * @param int $score
     * @return string
     */
    public static function getStrengthLabel($score) {
        if ($score < 30) return 'Sangat Lemah';
        if ($score < 50) return 'Lemah';
        if ($score < 70) return 'Sedang';
        if ($score < 90) return 'Kuat';
        return 'Sangat Kuat';
    }
}

/**
 * Validate password confirmation
 * @param string $password
 * @param string $confirm
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePasswordConfirmation($password, $confirm) {
    $errors = [];

    if ($password !== $confirm) {
        $errors[] = "Konfirmasi password tidak cocok";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
?>
