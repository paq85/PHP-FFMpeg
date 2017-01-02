<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\FFProbe\DataMapping;

use FFMpeg\Exception\LogicException;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\Coordinate\Dimension;

class Stream extends AbstractData
{
    /**
     * Returns true if the stream is an audio stream.
     *
     * @return Boolean
     */
    public function isAudio()
    {
        return $this->get('codec_type') === 'audio';
    }

    /**
     * Returns true if the stream is a video stream.
     *
     * @return Boolean
     */
    public function isVideo()
    {
        return $this->get('codec_type') === 'video';
    }

    /**
     * Returns the dimension of the video stream.
     *
     * @return Dimension
     *
     * @throws LogicException   In case the stream is not a video stream.
     * @throws RuntimeException In case the dimensions can not be extracted.
     */
    public function getDimensions()
    {
        if (!$this->isVideo()) {
            throw new LogicException('Dimensions can only be retrieved from video streams.');
        }

        $width = $this->get('width');
        $height = $this->get('height');

        if (null === $height || null === $width) {
            throw new RuntimeException('Unable to extract dimensions.');
        }

        return new Dimension($width, $height);
    }
}
