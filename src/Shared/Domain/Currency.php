<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * Money is kept in the smallest unit of its currency (ADR-010). Toman is a
 * display unit only: conversion happens in the formatter and gateway adapters.
 */
enum Currency: string
{
    case IRR = 'IRR';
}
