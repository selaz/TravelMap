<?php

namespace Selaz\Tools;

use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;

class ColoredLineFormatter extends LineFormatter
{
    public const MODE_COLOR_LEVEL_ALL = -1;
    public const MODE_COLOR_LEVEL_FIRST = 1;

    private const RESET = "\033[0m";

    /**
     * Color scheme - use ANSI colour sequences
     * @var string[]
     */
    private $colorScheme = [
        //symbol;extra;background
        100 => "\033[37;2;40m",
        200 => "\033[32;2;40m",
        250 => "\033[36;40m",
        300 => "\033[33;1;40m",
        400 => "\033[31;1;40m",
        500 => "\033[31;4;40m",
        550 => "\033[37;4;41m",
        600 => "\033[37;5;41m"
    ];

    /**
     * ColoredLineFormatter constructor.
     * @param string|null $format                     The format of the message
     * @param string|null $dateFormat                 The format of the timestamp: one supported by DateTime::format
     * @param bool        $allowInlineLineBreaks      Whether to allow inline line breaks in log entries
     * @param bool        $ignoreEmptyContextAndExtra
     * @param array<string>|null $colorScheme         @see ColoredLineFormatter::$colorScheme
     * @param int $colorMode                Whether we want to replace all '%level_name%' occurrences or only the first.
     *                                                Only useful if no %color_start%/%color_end% specified in $format
     */
    public function __construct(
        ?string $format = LineFormatter::SIMPLE_FORMAT,
        ?string $dateFormat = null,
        bool $allowInlineLineBreaks = false,
        bool $ignoreEmptyContextAndExtra = false,
        ?array $colorScheme = null,
        int $colorMode = self::MODE_COLOR_LEVEL_ALL
    ) {
        parent::__construct($format, $dateFormat, $allowInlineLineBreaks, $ignoreEmptyContextAndExtra);

        if (false === strpos($this->format, '%color_start%') && false === strpos($this->format, '%color_end%')) {
            $this->format = preg_replace(
                '/%level_name%/',
                '%color_start%%level_name%%color_end%',
                $this->format,
                $colorMode
            );
        }
        if (!is_null($colorScheme)) {
            $this->colorScheme = $colorScheme;
        }
    }

    /**
     * Formats a log record, with color.
     *
     * @param  LogRecord $record A record to format
     * @return string The formatted and colored record
     */
    public function format(LogRecord $record): string
    {
        $formatted = parent::format($record);
        $formatted = str_replace('%color_start%', $this->colorScheme[$record['level']], $formatted);
        $formatted = str_replace('%color_end%', self::RESET, $formatted);
        return $formatted;
    }
}