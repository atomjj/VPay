<?php
/*
 * Copyright (c) 2023. Ankio.  由CleanPHP4强力驱动。
 */

namespace library\qrcode\src;

use library\qrcode\src\Common\{EccLevel, ECICharset, MaskPattern, Mode, Version};
use library\qrcode\src\Data\{AlphaNum,
    Byte,
    ECI,
    Hanzi,
    Kanji,
    Number,
    QRCodeDataException,
    QRData,
    QRDataModeInterface,
    QRMatrix};
use library\qrcode\src\Decoder\{Decoder,
    DecoderResult,
    GDLuminanceSource,
    IMagickLuminanceSource,
    LuminanceSourceInterface,
    QRCodeDecoderException};
use library\qrcode\src\Output\{QRCodeOutputException, QROutputInterface};
use library\qrcode\src\Settings\SettingsContainerInterface;
use Throwable;
use function class_exists;
use function class_implements;
use function in_array;
use function mb_convert_encoding;
use function mb_internal_encoding;

/**
 * Turns a text string into a Model 2 QR Code
 *
 * @see https://github.com/kazuhikoarase/qrcode-generator/tree/master/php
 * @see https://www.qrcode.com/en/codes/model12.html
 * @see https://www.swisseduc.ch/informatik/theoretische_informatik/qr_codes/docs/qr_standard.pdf
 * @see https://en.wikipedia.org/wiki/QR_code
 * @see https://www.thonky.com/qr-code-tutorial/
 */
class QRCode
{

    /**
     * @deprecated 5.0.0 use Version::AUTO instead
     * @see Version::AUTO
     * @var int
     */
    public const VERSION_AUTO = Version::AUTO;

    /**
     * @deprecated 5.0.0 use MaskPattern::AUTO instead
     * @see MaskPattern::AUTO
     * @var int
     */
    public const MASK_PATTERN_AUTO = MaskPattern::AUTO;

    /**
     * @deprecated 5.0.0 use EccLevel::L instead
     * @see EccLevel::L
     * @var int
     */
    public const ECC_L = EccLevel::L;

    /**
     * @deprecated 5.0.0 use EccLevel::M instead
     * @see EccLevel::M
     * @var int
     */
    public const ECC_M = EccLevel::M;

    /**
     * @deprecated 5.0.0 use EccLevel::Q instead
     * @see EccLevel::Q
     * @var int
     */
    public const ECC_Q = EccLevel::Q;

    /**
     * @deprecated 5.0.0 use EccLevel::H instead
     * @see EccLevel::H
     * @var int
     */
    public const ECC_H = EccLevel::H;

    /**
     * @deprecated 5.0.0 use QROutputInterface::MARKUP_HTML instead
     * @see QROutputInterface::MARKUP_HTML
     * @var string
     */
    public const OUTPUT_MARKUP_HTML = QROutputInterface::MARKUP_HTML;

    /**
     * @deprecated 5.0.0 use QROutputInterface::MARKUP_SVG instead
     * @see QROutputInterface::MARKUP_SVG
     * @var string
     */
    public const OUTPUT_MARKUP_SVG = QROutputInterface::MARKUP_SVG;

    /**
     * @deprecated 5.0.0 use QROutputInterface::GDIMAGE_PNG instead
     * @see QROutputInterface::GDIMAGE_PNG
     * @var string
     */
    public const OUTPUT_IMAGE_PNG = QROutputInterface::GDIMAGE_PNG;

    /**
     * @deprecated 5.0.0 use QROutputInterface::GDIMAGE_JPG instead
     * @see QROutputInterface::GDIMAGE_JPG
     * @var string
     */
    public const OUTPUT_IMAGE_JPG = QROutputInterface::GDIMAGE_JPG;

    /**
     * @deprecated 5.0.0 use QROutputInterface::GDIMAGE_GIF instead
     * @see QROutputInterface::GDIMAGE_GIF
     * @var string
     */
    public const OUTPUT_IMAGE_GIF = QROutputInterface::GDIMAGE_GIF;

    /**
     * @deprecated 5.0.0 use QROutputInterface::STRING_JSON instead
     * @see QROutputInterface::STRING_JSON
     * @var string
     */
    public const OUTPUT_STRING_JSON = QROutputInterface::STRING_JSON;

    /**
     * @deprecated 5.0.0 use QROutputInterface::STRING_TEXT instead
     * @see QROutputInterface::STRING_TEXT
     * @var string
     */
    public const OUTPUT_STRING_TEXT = QROutputInterface::STRING_TEXT;

    /**
     * @deprecated 5.0.0 use QROutputInterface::IMAGICK instead
     * @see QROutputInterface::IMAGICK
     * @var string
     */
    public const OUTPUT_IMAGICK = QROutputInterface::IMAGICK;

    /**
     * @deprecated 5.0.0 use QROutputInterface::FPDF instead
     * @see QROutputInterface::FPDF
     * @var string
     */
    public const OUTPUT_FPDF = QROutputInterface::FPDF;

    /**
     * @deprecated 5.0.0 use QROutputInterface::EPS instead
     * @see QROutputInterface::EPS
     * @var string
     */
    public const OUTPUT_EPS = QROutputInterface::EPS;

    /**
     * @deprecated 5.0.0 use QROutputInterface::CUSTOM instead
     * @see QROutputInterface::CUSTOM
     * @var string
     */
    public const OUTPUT_CUSTOM = QROutputInterface::CUSTOM;

    /**
     * @deprecated 5.0.0 use QROutputInterface::MODES instead
     * @see QROutputInterface::MODES
     * @var string[]
     */
    public const OUTPUT_MODES = QROutputInterface::MODES;

    /**
     * The settings container
     *
     * @var QROptions|SettingsContainerInterface
     */
    protected SettingsContainerInterface $options;

    /**
     * A collection of one or more data segments of QRDataModeInterface instances to write
     *
     * @var QRDataModeInterface[]
     */
    protected array $dataSegments = [];

    /**
     * The luminance source for the reader
     */
    protected string $luminanceSourceFQN = GDLuminanceSource::class;

    /**
     * QRCode constructor.
     *
     * PHP8: accept iterable
     */
    public function __construct(SettingsContainerInterface $options = null)
    {
        $this->setOptions(($options ?? new QROptions));
    }

    /**
     * Sets an options instance
     */
    public function setOptions(SettingsContainerInterface $options): self
    {
        $this->options = $options;

        if ($this->options->readerUseImagickIfAvailable) {
            $this->luminanceSourceFQN = IMagickLuminanceSource::class;
        }

        return $this;
    }

    /**
     * Renders a QR Code for the given $data and QROptions, saves $file optionally
     *
     * @return mixed
     */
    public function render(string $data = null, string $file = null)
    {

        if ($data !== null) {
            /** @var QRDataModeInterface $dataInterface */
            foreach (Mode::INTERFACES as $dataInterface) {

                if ($dataInterface::validateString($data)) {
                    $this->addSegment(new $dataInterface($data));

                    break;
                }
            }
        }

        return $this->renderMatrix($this->getQRMatrix(), $file);
    }

    /**
     * Renders a QR Code for the given QRMatrix and QROptions, saves $file optionally
     *
     * @return mixed
     */
    public function renderMatrix(QRMatrix $matrix, string $file = null)
    {
        return $this->initOutputInterface($matrix)->dump($file ?? $this->options->cachefile);
    }

    /**
     * Returns a QRMatrix object for the given $data and current QROptions
     *
     * @throws QRCodeDataException
     */
    public function getQRMatrix(): QRMatrix
    {
        $matrix = (new QRData($this->options, $this->dataSegments))->writeMatrix();

        $maskPattern = $this->options->maskPattern === MaskPattern::AUTO
            ? MaskPattern::getBestPattern($matrix)
            : new MaskPattern($this->options->maskPattern);

        $matrix->setFormatInfo($maskPattern)->mask($maskPattern);

        return $this->addMatrixModifications($matrix);
    }

    /**
     * add matrix modifications after mask pattern evaluation and before handing over to output
     */
    protected function addMatrixModifications(QRMatrix $matrix): QRMatrix
    {

        if ($this->options->addLogoSpace) {
            $logoSpaceWidth = $this->options->logoSpaceWidth;
            $logoSpaceHeight = $this->options->logoSpaceHeight;

            // check whether one of the dimensions was omitted
            if ($logoSpaceWidth === null || $logoSpaceHeight === null) {
                $logoSpaceWidth = ($logoSpaceWidth ?? $logoSpaceHeight ?? 0);
                $logoSpaceHeight = null;
            }

            $matrix->setLogoSpace(
                $logoSpaceWidth,
                $logoSpaceHeight,
                $this->options->logoSpaceStartX,
                $this->options->logoSpaceStartY
            );
        }

        if ($this->options->addQuietzone) {
            $matrix->setQuietZone($this->options->quietzoneSize);
        }

        return $matrix;
    }

    /**
     * @deprecated 5.0.0 use QRCode::getQRMatrix() instead
     * @see QRCode::getQRMatrix
     * @codeCoverageIgnore
     */
    public function getMatrix(): QRMatrix
    {
        return $this->getQRMatrix();
    }

    /**
     * initializes a fresh built-in or custom QROutputInterface
     *
     * @throws QRCodeOutputException
     */
    protected function initOutputInterface(QRMatrix $matrix): QROutputInterface
    {
        $outputInterface = (QROutputInterface::MODES[$this->options->outputType] ?? null);

        if ($this->options->outputType === QROutputInterface::CUSTOM) {
            $outputInterface = $this->options->outputInterface;
        }

        if (!$outputInterface || !class_exists($outputInterface)) {
            throw new QRCodeOutputException('invalid output module');
        }

        if (!in_array(QROutputInterface::class, class_implements($outputInterface))) {
            throw new QRCodeOutputException('output module does not implement QROutputInterface');
        }

        return new $outputInterface($this->options, $matrix);
    }

    /**
     * checks if a string qualifies as numeric (convenience method)
     *
     * @deprecated 5.0.0 use Number::validateString() instead
     * @see Number::validateString
     * @codeCoverageIgnore
     */
    public function isNumber(string $string): bool
    {
        return Number::validateString($string);
    }

    /**
     * checks if a string qualifies as alphanumeric (convenience method)
     *
     * @deprecated 5.0.0 use AlphaNum::validateString() instead
     * @see AlphaNum::validateString
     * @codeCoverageIgnore
     */
    public function isAlphaNum(string $string): bool
    {
        return AlphaNum::validateString($string);
    }

    /**
     * checks if a string qualifies as Kanji (convenience method)
     *
     * @deprecated 5.0.0 use Kanji::validateString() instead
     * @see Kanji::validateString
     * @codeCoverageIgnore
     */
    public function isKanji(string $string): bool
    {
        return Kanji::validateString($string);
    }

    /**
     * a dummy (convenience method)
     *
     * @deprecated 5.0.0 use Byte::validateString() instead
     * @see Byte::validateString
     * @codeCoverageIgnore
     */
    public function isByte(string $string): bool
    {
        return Byte::validateString($string);
    }

    /**
     * Adds a data segment
     *
     * ISO/IEC 18004:2000 8.3.6 - Mixing modes
     * ISO/IEC 18004:2000 Annex H - Optimisation of bit stream length
     */
    public function addSegment(QRDataModeInterface $segment): self
    {
        $this->dataSegments[] = $segment;

        return $this;
    }

    /**
     * Clears the data segments array
     *
     * @codeCoverageIgnore
     */
    public function clearSegments(): self
    {
        $this->dataSegments = [];

        return $this;
    }

    /**
     * Adds a numeric data segment
     *
     * ISO/IEC 18004:2000 8.3.2 - Numeric Mode
     */
    public function addNumericSegment(string $data): self
    {
        return $this->addSegment(new Number($data));
    }

    /**
     * Adds an alphanumeric data segment
     *
     * ISO/IEC 18004:2000 8.3.3 - Alphanumeric Mode
     */
    public function addAlphaNumSegment(string $data): self
    {
        return $this->addSegment(new AlphaNum($data));
    }

    /**
     * Adds a Kanji data segment (Japanese 13-bit double-byte characters, Shift-JIS)
     *
     * ISO/IEC 18004:2000 8.3.5 - Kanji Mode
     */
    public function addKanjiSegment(string $data): self
    {
        return $this->addSegment(new Kanji($data));
    }

    /**
     * Adds a Hanzi data segment (simplified Chinese 13-bit double-byte characters, GB2312/GB18030)
     *
     * GBT18284-2000 Hanzi Mode
     */
    public function addHanziSegment(string $data): self
    {
        return $this->addSegment(new Hanzi($data));
    }

    /**
     * Adds an 8-bit byte data segment
     *
     * ISO/IEC 18004:2000 8.3.4 - 8-bit Byte Mode
     */
    public function addByteSegment(string $data): self
    {
        return $this->addSegment(new Byte($data));
    }

    /**
     * Adds a standalone ECI designator
     *
     * The ECI designator must be followed by a Byte segment that contains the string encoded according to the given ECI charset
     *
     * ISO/IEC 18004:2000 8.3.1 - Extended Channel Interpretation (ECI) Mode
     */
    public function addEciDesignator(int $encoding): self
    {
        return $this->addSegment(new ECI($encoding));
    }

    /**
     * Adds an ECI data segment (including designator)
     *
     * The given string will be encoded from mb_internal_encoding() to the given ECI character set
     *
     * I hate this somehow, but I'll leave it for now
     *
     * @throws QRCodeException
     */
    public function addEciSegment(int $encoding, string $data): self
    {
        // validate the encoding id
        $eciCharset = new ECICharset($encoding);
        // get charset name
        $eciCharsetName = $eciCharset->getName();
        // convert the string to the given charset
        if ($eciCharsetName !== null) {
            $data = mb_convert_encoding($data, $eciCharsetName, mb_internal_encoding());

            return $this
                ->addEciDesignator($eciCharset->getID())
                ->addByteSegment($data);
        }

        throw new QRCodeException('unable to add ECI segment');
    }

    /**
     * Reads a QR Code from a given file
     *
     * @noinspection PhpUndefinedMethodInspection
     *      * @throws Throwable|QRCodeDecoderException
     */
    public function readFromFile(string $path): DecoderResult
    {
        return $this->readFromSource($this->luminanceSourceFQN::fromFile($path, $this->options));
    }

    /**
     * Reads a QR Code from the given data blob
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    public function readFromBlob(string $blob): DecoderResult
    {
        return $this->readFromSource($this->luminanceSourceFQN::fromBlob($blob, $this->options));
    }

    /**
     * Reads a QR Code from the given luminance source
     * @throws
     */
    public function readFromSource(LuminanceSourceInterface $source): DecoderResult
    {
        return (new Decoder)->decode($source);
    }

}
