<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 🔐 ALLOWED EMAIL VALIDATION RULE
 *
 * Validates that an email is allowed to register/login
 * Supports both domain-based and specific email allowlists
 */
class AllowedEmail implements ValidationRule
{
    /**
     * 📧 ALLOWED DOMAINS
     *
     * Domains that are allowed to register
     * Add your allowed domains here
     */
    protected array $allowedDomains = [
        'benjh.com',
        'blindsoutlet.co.uk',
    ];

    /**
     * 📧 ALLOWED SPECIFIC EMAILS
     *
     * Specific email addresses that are allowed
     * Useful for individual access control
     */
    protected array $allowedEmails = [
        'admin@example.com',
        'support@yourdomain.com',
    ];

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || empty($value)) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        // Check if it's a valid email format first
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        $email = strtolower(trim($value));
        $domain = substr(strrchr($email, '@'), 1);

        // Check if the specific email is in the allowlist
        if (in_array($email, array_map('strtolower', $this->allowedEmails))) {
            return; // Email is allowed
        }

        // Check if the domain is in the allowlist
        if (in_array($domain, array_map('strtolower', $this->allowedDomains))) {
            return; // Domain is allowed
        }

        // If neither email nor domain is allowed, fail validation
        $fail('This email address is not authorized to access this system. Please contact support if you believe this is an error.');
    }

    /**
     * 🔧 SET ALLOWED DOMAINS
     *
     * Dynamically set allowed domains
     */
    public function setAllowedDomains(array $domains): self
    {
        $this->allowedDomains = $domains;

        return $this;
    }

    /**
     * 🔧 SET ALLOWED EMAILS
     *
     * Dynamically set allowed emails
     */
    public function setAllowedEmails(array $emails): self
    {
        $this->allowedEmails = $emails;

        return $this;
    }
}
