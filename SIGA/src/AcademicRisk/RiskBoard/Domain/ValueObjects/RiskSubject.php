<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\ValueObjects;

/**
 * What kind of record a risk item points at. RE-04 requires every item
 * to link straight to the affected record, and "group" and "teacher"
 * live on different screens — this is the domain saying which, without
 * knowing that screens or URLs exist.
 */
enum RiskSubject: string
{
    case Group = 'group';
    case Teacher = 'teacher';
}
