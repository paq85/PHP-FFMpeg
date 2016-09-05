<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Media;

use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Filters\Audio\SimpleFilter;
use FFMpeg\Exception\InvalidArgumentException;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\Filters\Video\VideoFilters;
use FFMpeg\Filters\FilterInterface;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Format\ProgressableInterface;
use FFMpeg\Format\AudioInterface;
use FFMpeg\Format\VideoInterface;
use Neutron\TemporaryFilesystem\Manager as FsManager;
use Psr\Log\LoggerAwareTrait;

class Video extends Audio
{
    use LoggerAwareTrait;

    /**
     * {@inheritdoc}
     *
     * @return VideoFilters
     */
    public function filters()
    {
        return new VideoFilters($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return Video
     */
    public function addFilter(FilterInterface $filter)
    {
        $this->filters->add($filter);

        return $this;
    }

    /**
     * Exports the video in the desired format, applies registered filters.
     *
     * @param FormatInterface $format
     * @param string          $outputPathfile
     *
     * @return Video
     *
     * @throws RuntimeException
     */
    public function save(FormatInterface $format, $outputPathfile)
    {
        $commands = array('-y', '-i', $this->pathfile);

        $filters = clone $this->filters;
        $filters->add(new SimpleFilter($format->getExtraParams(), 10));

        if ($this->driver->getConfiguration()->has('ffmpeg.threads')) {
            $filters->add(new SimpleFilter(array('-threads', $this->driver->getConfiguration()->get('ffmpeg.threads'))));
        }
        if ($format instanceof VideoInterface) {
            if (null !== $format->getVideoCodec()) {
                $filters->add(new SimpleFilter(array('-vcodec', $format->getVideoCodec())));
            }
        }
        if ($format instanceof AudioInterface) {
            if (null !== $format->getAudioCodec()) {
                $filters->add(new SimpleFilter(array('-acodec', $format->getAudioCodec())));
            }
        }

        foreach ($filters as $filter) {
            $commands = array_merge($commands, $filter->apply($this, $format));
        }

        if ($format instanceof AudioInterface) {
            if (null !== $format->getAudioKiloBitrate()) {
                $commands[] = '-b:a';
                $commands[] = $format->getAudioKiloBitrate() . 'k';
            }
            if (null !== $format->getAudioChannels()) {
                $commands[] = '-ac';
                $commands[] = $format->getAudioChannels();
            }
        }

        $fs = FsManager::create();
        $fsId = uniqid('ffmpeg-passes');
        $passPrefix = $fs->createTemporaryDirectory(0777, 50, $fsId) . '/' . uniqid('pass-');
        $passes = array();
        $totalPasses = $format->getPasses();

        if (1 > $totalPasses) {
            throw new InvalidArgumentException('Pass number should be a positive value.');
        }

        for ($i = 1; $i <= $totalPasses; $i++) {
            $pass = $commands;

            if ($totalPasses > 1) {
                $pass[] = '-pass';
                $pass[] = $i;
                $pass[] = '-passlogfile';
                $pass[] = $passPrefix;
            }

            $pass[] = $outputPathfile;

            $passes[] = $pass;
        }

        $failure = null;

        foreach ($passes as $pass => $passCommands) {
            try {
                /** add listeners here */
                $listeners = null;

                if ($format instanceof ProgressableInterface) {
                    $listeners = $format->createProgressListener($this, $this->ffprobe, $pass + 1, $totalPasses);
                }

                $output = $this->driver->command($passCommands, false, $listeners);
                if ($this->logger) {
                    $this->logger->info($output);
                }
            } catch (ExecutionFailureException $e) {
                $failure = $e;
                break;
            }
        }

        $fs->clean($fsId);

        if (null !== $failure) {
            throw new RuntimeException('Encoding failed', $failure->getCode(), $failure);
        }

        return $this;
    }

    /**
     * Gets the frame at timecode.
     *
     * @param  TimeCode $at
     * @return Frame
     */
    public function frame(TimeCode $at)
    {
        return new Frame($this, $this->driver, $this->ffprobe, $at);
    }
}
