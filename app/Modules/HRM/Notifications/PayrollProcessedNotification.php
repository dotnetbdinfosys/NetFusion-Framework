<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Notifications/PayrollProcessedNotification.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Notifications
| Purpose   : Multi-channel notification alerting employees of processed payrolls.
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

namespace NetFusion\Modules\HRM\Notifications;

use NetFusion\Core\Notifications\Notification;
use NetFusion\Modules\HRM\Mail\PayslipMail;
use NetFusion\Modules\HRM\Models\Employee;
use NetFusion\Modules\HRM\Models\Payroll;

/**
 * NetFusion HRM Payroll Processed Notification
 *
 * Dispatches an email payslip and records an in-app database notification.
 *
 * @package NetFusion\Modules\HRM\Notifications
 * @version 1.0.0
 */
class PayrollProcessedNotification extends Notification
{
    /**
     * @param Payroll $payroll
     */
    public function __construct(
        public readonly Payroll $payroll,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * {@inheritDoc}
     */
    public function toMail(object $notifiable): PayslipMail
    {
        /** @var Employee $employee */
        $employee = $notifiable;
        return new PayslipMail($employee, $this->payroll);
    }

    /**
     * {@inheritDoc}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'       => 'Salary Processed',
            'month'       => $this->payroll->month,
            'year'        => $this->payroll->year,
            'net_salary'  => $this->payroll->net_salary,
            'message'     => "Your salary for {$this->payroll->month}/{$this->payroll->year} has been processed.",
            'processed_at'=> date('Y-m-d H:i:s'),
        ];
    }
}
