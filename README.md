# PEM Task Compiler (PHP)

This repository contains a PHP implementation of a way to compile PEM tasks (conforming the PEM Installation API) into separate files that can be served conditionnaly.

## Usage/Example

You have a task `task.html` containing all the modules, the main task, hints, solution, etc. and, in the context of a contest, you don't want users to see the solution. You can then use

    $taskCompiler = new Task($taskJson, $taskBasePath, true);
    $taskCompiler-> generate($dstDir, $dstUrl);

where

- `$taskJson` is the json as described in the PEM Installation API documentation
- `$taskBasePath` is the base path of the files it references
- `$dstDir` is the directory where you want the compiled files to appear
- `$dstUrl` is the base URL on which the files will be accessed

This will createthe following files in `$dstDir`:

- `index.html` containing the content that should be accessed by contestants (no solution nor grading), with all js and css files compiled
- `bebras.js` containing the json describing the task
- `solution.html` containing the solution
- `grader.js` containing the grader
- `.htaccess` denying the access to all previous files

Then it's up to you serve the files according to user rights.

## Installation

    composer install France-IOI/pem-task-compiler

## TODO

- link to the Google doc
- a simple example (the one from svn is way too convoluted)
- `.htaccess` generation should be optional
- document all functions (not just `generate`), as used by `beaver-platform`
