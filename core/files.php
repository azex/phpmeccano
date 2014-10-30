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
    
    public static function move($sourcePath, $destPath, $mergeDirs = FALSE, $replaceFiles = FALSE, $skipNotReadable = FALSE, $skipWriteProtected = FALSE, $skipConflicts = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        $sourcePath = rtrim(preg_replace('#[/]+#', '/', $sourcePath), '/');
        $destPath = rtrim(preg_replace('#[/]+#', '/', $destPath), '/');
        if (!file_exists($sourcePath)) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp("move: source [$sourcePath] was not found");
            return FALSE;
        }
        if ($sourcePath == $destPath) {
            self::setErrId(ERROR_NOT_EXECUTED);            self::setErrExp("move: unable to replace item with itself");
            return FALSE;
        }
        // source is file or symbolic link
        if (is_file($sourcePath) || is_link($sourcePath)) {
            if (is_dir($destPath)) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("move: unable to replace directory [$destPath] with file");
                return FALSE;
            }
            else {
                $sourceDirPath = dirname($sourcePath);
                if (!is_writable($sourceDirPath)) {
                    self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("move: source directory [$sourceDirPath] is write-protected");
                    return FALSE;
                }
                $destDirPath = dirname($destPath);
                if (is_dir($destDirPath)) {
                    if (!is_writable($destDirPath)) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("move: destination directory [$destDirPath] is write-protected");
                        return FALSE;
                    }
                }
                else {
                    self::setErrId(ERROR_NOT_FOUND);                    self::setErrExp("move: directory [$destDirPath] was not found");
                    return FALSE;
                }
                if (is_file($destPath) || is_link($destPath)) {
                    if (!$replaceFiles) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('move: replacing of files is not allowed');
                        return FALSE;
                    }
                }
                if (@rename($sourcePath, $destPath)) {
                    return TRUE;
                }
                else {
                    self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("move: unable to move file [$sourcePath]. Probably you tried to move not readable file to another disk partition.");
                    return FALSE;
                }
            }
        }
        // source is directory
        elseif (is_dir($sourcePath)) {
            if (!is_readable($sourcePath)) {
                self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("move: source directory [$sourcePath] is not readable");
                return FALSE;
            }
            if (is_file($destPath)) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("move: unable to replace file [$destPath] with directory [$sourcePath]");
                return FALSE;
            }
            $destDirPath = dirname($destPath);
            if (!is_dir($destPath) && !is_link($destPath)) {
                if (is_writable($destDirPath)) {
                    mkdir($destPath);
                }
                else {
                    self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("move: destination directory [$destDirPath] is write-protected");
                    return FALSE;
                }
            }
            elseif (!$mergeDirs) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("move: merging of directories is not allowed");
                return FALSE;
            }
            elseif (!is_writable($destPath)) {
                self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("move: destination directory [$destPath] is write-protected");
                return FALSE;
            }
            $stack = array($sourcePath);
            $divPosition = strlen($sourcePath) + 1;
            while (count($stack)) {
                $sourceDirPath = array_pop($stack);
                $sourceDirWriteStatus = is_writable($sourceDirPath);
                $dirTree[] = $sourceDirPath;
                $destDirPath = rtrim("$destPath/".substr($sourceDirPath, $divPosition), "/");
                $destDirWriteStatus = is_writable($destDirPath);
                foreach (array_diff(scandir($sourceDirPath), array('.', '..')) as $sourceItemName) {
                    $sourceFullPath = "$sourceDirPath/$sourceItemName";
                    $destFullPath = "$destDirPath/$sourceItemName";
                    if ((is_file($sourceFullPath) || is_link($sourceFullPath))) {
                        $sourceIsNotDir = 1;
                    }
                    else {
                        $sourceIsNotDir = 0;
                    }
                    if ($sourceIsNotDir && $sourceDirWriteStatus && $destDirWriteStatus) { // file handling
                        if ((is_dir($destFullPath) && !is_link($destFullPath)) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("move: unable to replace directory [$destFullPath] with file [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (!file_exists($destFullPath)) {
                            if (!@rename($sourceFullPath, $destFullPath) && !$skipNotReadable) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("move: unable to move file [$sourceFullPath]. Probably you tried to move not readable file to another disk partition.");
                                return FALSE;
                            }
                        }
                        elseif (file_exists($destFullPath) && $replaceFiles) {
                            if (!@rename($sourceFullPath, $destFullPath) && !$skipNotReadable) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("move: unable to move file [$sourceFullPath]. Probably you tried to move not readable file to another disk partition.");
                                return FALSE;
                            }
                        }
                    }
                    elseif (!$sourceIsNotDir) { // directory handling
                        if (is_file($destFullPath) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("move: unable to replace file [$destFullPath] with directory [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_readable($sourceFullPath) && is_dir($destFullPath)) {
                            $stack[] = $sourceFullPath;
                        }
                        elseif (is_readable($sourceFullPath) && !file_exists($destFullPath) && $destDirWriteStatus) {
                            $stack[] = $sourceFullPath;
                            mkdir($destFullPath);
                        }
                        elseif (!is_readable($sourceFullPath) && !$skipNotReadable) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("move: unable to read directory [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_dir($destFullPath) && !is_writable($destFullPath) && !$skipWriteProtected) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("move: unable to write into [$destFullPath]");
                            return FALSE;
                        }
                    }
                    elseif (!$sourceDirWriteStatus && !$skipWriteProtected) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                        self::setErrExp("move: unable to move file [$sourceFullPath] because source directory [$sourceDirPath] is write-protected");
                        return FALSE;
                    }
                    elseif (!$destDirWriteStatus && !$skipWriteProtected) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                        self::setErrExp("move: unable to move file [$sourceFullPath] because destination directory [$destDirPath] is write-protected");
                        return FALSE;
                    }
                }
            }
            $dirTree = array_reverse($dirTree);
            foreach ($dirTree as $dirPath) {
                $parentDir = dirname($dirPath);
                $parentDirWriteStatus = is_writable($parentDir);
                $dirIsNotEmpty = array_diff(scandir($dirPath), array('.', '..'));
                if ($dirIsNotEmpty && !$skipConflicts) {
                    self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("source directory [$dirPath] is not empty");
                    return  FALSE;
                }
                elseif (!$parentDirWriteStatus && !$skipWriteProtected) {
                    self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("unable to remove directory [$dirPath] because parent directory [$parentDir] is write-protected");
                    return  FALSE;
                }
                elseif (!$dirIsNotEmpty && $parentDirWriteStatus) {
                    rmdir($dirPath);
                }
            }
            return TRUE;
        }
    }
    
}
