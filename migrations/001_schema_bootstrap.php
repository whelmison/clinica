<?php

return static function (mysqli $conn): void {
    app_install_schema($conn);
};
