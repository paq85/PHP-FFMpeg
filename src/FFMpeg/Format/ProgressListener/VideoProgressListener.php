<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Format\ProgressListener;

/**
 * Parses ffmpeg stderr progress information for video files. An example:
 *
 * <pre>
 *       frame=  171 fps=0.0 q=10.0 size=      18kB time=00:00:05.72 bitrate=  26.4kbits/s dup=8 drop=0
 * </pre>
 * 
 * FFMPEG 3.x
 * <pre>
 *      frame=  343 fps= 13 q=-1.0 Lsize=    3121kB time=00:00:11.49 bitrate=2224.2kbits/s speed=0.442x
 * </pre>
 *
 * @author Robert Gruendler <r.gruendler@gmail.com>
 */
class VideoProgressListener extends AbstractProgressListener
{
    public function getPattern()
    {
        return '/size=(.*?) time=(.*?) /';
    }
}
