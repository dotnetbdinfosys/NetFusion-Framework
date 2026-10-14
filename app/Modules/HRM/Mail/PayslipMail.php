<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Mail/PayslipMail.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Mail
| Purpose   : Mailable class sending formatted monthly salary payslips to employees.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Modules\HRM\Mail;

use NetFusion\Core\Mail\Mailable;
use NetFusion\Modules\HRM\Models\Employee;
use NetFusion\Modules\HRM\Models\Payroll;

/**
 * NetFusion HRM Payslip Mail
 *
 * Formats and delivers salary breakdown payslips directly to employee inboxes.
 *
 * @package NetFusion\Modules\HRM\Mail
 * @version 1.0.0
 */
class PayslipMail extends Mailable
{
    /**
     * @param Employee $employee
     * @param Payroll  $payroll
     */
    public function __construct(
        public readonly Employee $employee,
        public readonly Payroll  $payroll,
    ) {}

    /**
     * Build the message.
     */
    public function build(): static
    {
        $name    = $this->employee->getFullName();
        $month   = sprintf('%02d', $this->payroll->month);
        $year    = $this->payroll->year;
        $basic   = number_format((float) $this->payroll->basic_salary, 2);
        $allow   = number_format((float) $this->payroll->allowances, 2);
        $deduct  = number_format((float) $this->payroll->deductions, 2);
        $net     = number_format((float) $this->payroll->net_salary, 2);
        $curr    = (string) config('hrm.currency', 'BDT');

        $html = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px;">
    <h2 style="color: #1e293b; border-bottom: 2px solid #3b82f6; padding-bottom: 8px;">NetFusion HRM — Salary Payslip</h2>
    <p>Dear <strong>{$name}</strong>,</p>
    <p>Your salary for <strong>{$month}/{$year}</strong> has been processed.</p>
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr style="background: #f8fafc;"><td style="padding: 8px; border: 1px solid #e2e8f0;">Basic Salary</td><td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right;">{$curr} {$basic}</td></tr>
        <tr><td style="padding: 8px; border: 1px solid #e2e8f0;">Allowances</td><td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right; color: #16a34a;">+ {$curr} {$allow}</td></tr>
        <tr style="background: #f8fafc;"><td style="padding: 8px; border: 1px solid #e2e8f0;">Deductions</td><td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right; color: #dc2626;">- {$curr} {$deduct}</td></tr>
        <tr style="background: #e0f2fe; font-weight: bold;"><td style="padding: 8px; border: 1px solid #e2e8f0;">Net Payable Salary</td><td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right; color: #0284c7;">{$curr} {$net}</td></tr>
    </table>
    <p style="font-size: 12px; color: #64748b;">Generated automatically by DotNetBD NetFusion Framework.</p>
</div>
HTML;

        return $this->subject("Salary Payslip for {$month}/{$year} - {$name}")
                    ->html($html);
    }
}
