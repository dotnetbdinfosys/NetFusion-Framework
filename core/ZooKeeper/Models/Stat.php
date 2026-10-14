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

namespace NetFusion\Core\ZooKeeper\Models;

/**
 * Metadata stat record for a ZNode (czxid, mzxid, ctime, mtime, version, cversion, ephemeralOwner, dataLength, numChildren).
 */
class Stat
{
    public int $czxid;
    public int $mzxid;
    public int $pzxid;
    public int $ctime;
    public int $mtime;
    public int $version;
    public int $cversion;
    public int $aversion;
    public int $ephemeralOwner;
    public int $dataLength;
    public int $numChildren;

    public function __construct(
        int $czxid = 0,
        int $mzxid = 0,
        int $ctime = 0,
        int $mtime = 0,
        int $version = 0,
        int $cversion = 0,
        int $ephemeralOwner = 0,
        int $dataLength = 0,
        int $numChildren = 0
    ) {
        $this->czxid = $czxid;
        $this->mzxid = $mzxid;
        $this->pzxid = $czxid;
        $this->ctime = $ctime > 0 ? $ctime : time();
        $this->mtime = $mtime > 0 ? $mtime : $this->ctime;
        $this->version = $version;
        $this->cversion = $cversion;
        $this->aversion = 0;
        $this->ephemeralOwner = $ephemeralOwner;
        $this->dataLength = $dataLength;
        $this->numChildren = $numChildren;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'czxid' => $this->czxid,
            'mzxid' => $this->mzxid,
            'pzxid' => $this->pzxid,
            'ctime' => $this->ctime,
            'mtime' => $this->mtime,
            'version' => $this->version,
            'cversion' => $this->cversion,
            'aversion' => $this->aversion,
            'ephemeralOwner' => $this->ephemeralOwner,
            'dataLength' => $this->dataLength,
            'numChildren' => $this->numChildren,
        ];
    }
}
