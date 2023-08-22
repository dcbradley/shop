<?php

# This script is invoked when genrating PDFs with wkhtmltopdf.  The
# client is not authenticated and is only allowed to originate on the
# web server.

const INTERNAL_LOGIN = true;
require_once "index.php";
