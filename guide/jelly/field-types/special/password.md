#### `Jelly_Field_Password`

Represents an password. This automatically sets a validation callback that hashes the password after it's validated. That password is hashed only when it has changed.

[!!] Note: password validation (like password confirmation, length checks, etc.) should be done externally, don't define these rules in the field's settings. Please see the [external validation](validation#external-validation) part of the guide.

 * **`hash_with`** — A valid PHP callback to use for hashing the password. Defaults to `sha1`.

[API documentation](../api/Jelly_Field_Password)