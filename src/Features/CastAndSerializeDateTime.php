<?php

namespace Atamso\Model\Features;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * @method bool hasCast($key, $types = null)
 */
trait CastAndSerializeDateTime
{

    /**
     * @var string
     */
    protected $dateFormat;

    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->format(
            $this->getDateFormat()
        );
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param mixed $value
     *
     * @return \Illuminate\Support\Carbon
     */
    protected function asDateTime($value)
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof CarbonInterface) {
            return Date::instance($value);
        }

        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return Date::parse(
                $value->format('Y-m-d H:i:s.u'), $value->getTimezone()
            );
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Date::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return Date::instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }

        $format = $this->getDateFormat();

        try {
            $date = Date::createFromFormat($format, $value);
        } catch (InvalidArgumentException $e) {
            $date = false;
        }

        return $date ?: Date::parse($value);
    }

    protected function isStandardDateFormat($value)
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    public function setDateFormat($format)
    {
        $this->dateFormat = $format;

        return $this;
    }

    protected function asDate($value)
    {
        return $this->asDateTime($value)->startOfDay();
    }

    protected function asTimestamp($value)
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date instanceof \DateTimeImmutable ?
            CarbonImmutable::instance($date)->toJSON() :
            Carbon::instance($date)->toJSON();
    }

    protected function isDateCastableWithCustomFormat($key)
    {
        return $this->hasCast($key, ['custom_datetime', 'immutable_custom_datetime']);
    }

    protected function isDateAttribute($key)
    {
        return $this->isDateCastable($key);
    }

    protected function isDateCastable($key)
    {
        return $this->hasCast($key, ['date', 'datetime', 'immutable_date', 'immutable_datetime']);
    }

}
