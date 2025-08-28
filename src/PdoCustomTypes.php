<?php
/**
 *
 * This file is part of Atlas for PHP.
 *
 * @license https://opensource.org/licenses/MIT MIT
 *
 */
declare(strict_types=1);

namespace Atlas\Pdo;

/**
 * @phpstan-type connection_entry array<string, ?Connection>
 *
 * @phpstan-type connection_store_array array{
 *      DEFAULT: ?Connection,
 *      READ: connection_entry,
 *      WRITE: connection_entry
 * }
 *
 * @phpstan-type dsn_args_array array{
 *       dsn: string,
 *       username?: string,
 *       password?: string,
 *       options?: array<int, mixed>
 *   }
 *
 * @phpstan-type log_entry_array array{
 *       start: float,
 *       finish: ?float,
 *       duration: ?float,
 *       performed: ?bool,
 *       statement: ?string,
 *       values: array<array-key, mixed>,
 *       trace: ?string,
 *        connection?: string
 *  }
 *
 * @phpstan-type fetch_assoc_array mixed[]
 *
 * @phpstan-type fetch_string_mixed_array array<string, mixed>
 *
 */
final class PdoCustomTypes
{
}
