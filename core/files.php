<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace core;

require_once 'swconst.php';

/**
 * Description of files
 *
 * @author azex
 */
class Files {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    
    private static function setErrId($id) {
        self::$errid = $id;
    }
    
    private static function setErrExp($exp) {
        self::$errexp = $exp;
    }

    public static function errId() {
        return self::$errid;
    }
    
    public static function errExp() {
        return self::$errexp;
    }
    
    public static function copy($sourcePath, $destPath, $mergeDirs = FALSE, $rewriteFiles = FALSE, $skipNotReadable = FALSE, $skipNotWritable = FALSE, $skipConflicts = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        $sourcePath = rtrim(preg_replace('#(/+)|(\\\+)#', '/', $sourcePath), '/');
        $destPath = rtrim(preg_replace('#(/+)|(\\\+)#', '/', $destPath), '/');
        if (!file_exists($sourcePath)) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('copy: source was not found');
            return FALSE;
        }
        elseif (!is_readable($sourcePath)) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp('copy: source is not readable');
            return FALSE;
        }
        if (is_link($sourcePath)) {
            self::setErrId(ERROR_INCORRECT_DATA);        self::setErrExp('copy: unable to copy symbolic link');
            return FALSE;
        }
        elseif (is_dir($sourcePath)) { // source is directory
            if (is_file($destPath) || is_link($destPath)) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('copy: unable to replace file/link with directory');
                return FALSE;
            }
            $destDirPath = dirname($destPath);
            if (!is_dir($destPath)) {
                if (is_writable($destDirPath)) {
                    mkdir($destPath);
                }
                else {
                    self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp('copy: unable to create destination directory');
                    return FALSE;
                }
            }
            elseif (!is_writable($destPath)) {
                self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp('copy: destination directory is not writable');
                return FALSE;
            }
            elseif (!$mergeDirs) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('copy: merging of directories is not allowed');
                return FALSE;
            }
            $stack = array($sourcePath);
            $divPosition = strlen($sourcePath) + 1;
            while (count($stack)) {
                $sourceDirPath = array_pop($stack);
                foreach (array_diff(scandir($sourceDirPath), array('.', '..')) as $sourceItemName) {
                    $sourceFullPath = "$sourceDirPath/$sourceItemName";
                    $destFullPath = "$destPath/".  substr($sourceFullPath, $divPosition);
                    if (is_file($sourceFullPath) && !is_link($sourceFullPath)) { // file handling
                        if (is_link($destFullPath) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("copy: unable to copy file [$sourceFullPath] instead of link [$destFullPath]");
                            return FALSE;
                        }
                        elseif (is_dir($destFullPath) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("copy: unable to copy file [$sourceFullPath] instead of directory [$destFullPath]");
                            return FALSE;
                        }
                        elseif (is_readable($sourceFullPath) && !file_exists($destFullPath)) {
                            copy($sourceFullPath, $destFullPath);
                        }
                        elseif (is_readable($sourceFullPath) && is_file($destFullPath) && is_writable($destFullPath) && $rewriteFiles) {
                            copy($sourceFullPath, $destFullPath);
                        }
                        elseif (is_readable($sourceFullPath) && is_file($destFullPath) && !is_writable($destFullPath) && !$skipNotWritable && $rewriteFiles) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("copy: unable to rewrite [$destFullPath]");
                            return FALSE;
                        }
                        elseif (!is_readable($sourceFullPath) && !$skipNotReadable) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("copy: unable to read file [$sourceFullPath]");
                            return FALSE;
                        }
                    }
                    elseif (is_dir($sourceFullPath) && !is_link($sourceFullPath)) { // directory handling
                        if ((is_file($destFullPath) || is_link($destFullPath)) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("copy: unable to copy directory [$sourceFullPath] instead of file [$destFullPath]");
                            return FALSE;
                        }
                        elseif (is_readable($sourceFullPath) && is_dir($destFullPath) && is_writable($destFullPath)) {
                            $stack[] = $sourceFullPath;
                        }
                        elseif (is_readable($sourceFullPath) && !file_exists($destFullPath)) {
                            $stack[] = $sourceFullPath;
                            mkdir($destFullPath);
                        }
                        elseif (!is_readable($sourceFullPath) && !is_dir($destFullPath) && !is_file($destFullPath) && $skipNotReadable) {
                            mkdir($destFullPath);
                        }
                        elseif (!is_readable($sourceFullPath) && !$skipNotReadable) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("copy: unable to read directory [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_dir($destFullPath) && !is_writable($destFullPath) && !$skipNotWritable) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("copy: unable to write into [$destFullPath]");
                            return FALSE;
                        }
                    }
                }
            }
            return TRUE;
        }
        elseif (is_file($sourcePath)) { // source is file
            if (is_dir($destPath)) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('copy: unable to replace directory with file');
                return FALSE;
            }
            else {
                if (is_file($destPath) && !is_link($destPath)) {
                    if (!is_writable($destPath)) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp('copy: unable to replace destination file');
                        return FALSE;
                    }
                    elseif (!$rewriteFiles) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('copy: replacing of files is not allowed');
                        return FALSE;
                    }
                    else {
                        copy($sourcePath, $destPath);
                        return TRUE;
                    }
                }
                else {
                    $destDirPath = dirname($destPath);
                    if (is_dir($destDirPath) && !is_link($destDirPath)) {
                        if (!is_writable($destDirPath)) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp('copy: destination directory is write-protected');
                            return FALSE;
                        }
                        else {
                            copy($sourcePath, $destPath);
                            return TRUE;
                        }
                    }
                    elseif (is_link($destDirPath)) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('copy: unable to copy file into the link');
                        return FALSE;
                    }
                    else {
                        self::setErrId(ERROR_NOT_FOUND);                    self::setErrExp('copy: destination directory was not found');
                        return FALSE;
                    }
                }
            }
        }
    }
    
}
