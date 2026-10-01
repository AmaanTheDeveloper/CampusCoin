<?php

namespace App\Support\Demo;

/**
 * Stable identifiers for the local/demo lending ecosystem.
 * DEVELOPMENT / DEMO ONLY — never use these credentials in production.
 */
final class LendingDemoIds
{
    public const BANK_SLUG = 'campus-demo-bank';

    public const BANK_NAME = 'Campus Demo Bank';

    public const BANK_EMAIL = 'demo@campusbank.test';

    public const BANK_REGISTRATION = 'DEMO-BANK-001';

    public const OWNER_EMAIL = 'bank.admin@campusbank.test';

    public const OWNER_PASSWORD = 'CampusBankDemo@2026!';

    public const OFFICER_EMAIL = 'bank.officer@campusbank.test';

    public const OFFICER_PASSWORD = 'CampusOfficerDemo@2026!';

    public const STUDENT_EMAIL = 'student.demo@campusbank.test';

    public const STUDENT_PASSWORD = 'StudentDemo@2026!';

    public const STUDENT_NAME = 'Demo Student';

    public const ADMIN_EMAIL = 'admin.demo@platform.test';

    public const ADMIN_PASSWORD = 'AdminDemo@2026!';

    public const PRODUCT_SLUG = 'campus-student-support-loan';

    public const PRODUCT_NAME = 'Campus Student Support Loan';

    /** Walkthrough application — starts SUBMITTED for bank review. */
    public const APP_WALKTHROUGH_REFERENCE = 'APP-DEMO-WALKTHROUGH';

    /** Optional second application advanced to ACTIVE repayment (no seeded payments). */
    public const APP_ACTIVE_REFERENCE = 'APP-DEMO-ACTIVE';

    public const REQUESTED_AMOUNT = '60000.00';

    public const REQUESTED_TENURE_MONTHS = 12;
}
