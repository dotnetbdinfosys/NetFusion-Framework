<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Media\Enums;

enum RtcpType: int
{
    case SR = 200;    // Sender Report
    case RR = 201;    // Receiver Report
    case SDES = 202;  // Source Description
    case BYE = 203;   // Goodbye
    case APP = 204;   // Application-defined
    case RTPFB = 205; // Transport Layer Feedback (e.g. NACK)
    case PSFB = 206;  // Payload-Specific Feedback (e.g. PLI, REMB)
}
