<?php

namespace App\Exceptions;

use RuntimeException;

/** Infrastructure/integrity failure, not an observation about the external resource. */
class ArtifactStorageException extends RuntimeException {}
