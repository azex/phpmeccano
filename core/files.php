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

    public static function copy($sourcePath, $destPath, $mergeDirs = FALSE, $rewriteFiles = FALSE, $skipExistentFiles = FALSE, $skipNotReadable = FALSE, $skipWriteProtected = FALSE, $skipConflicts = FALSE, $removeConflictFiles = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        $sourcePath = rtrim(preg_replace('#[/]+#', '/', $sourcePath), '/');
        $destPath = rtrim(preg_replace('#[/]+#', '/', $destPath), '/');
        if (!file_exists($sourcePath)) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp('copy: source was not found');
            return FALSE;
        }
        elseif (!is_readable($sourcePath)) {
            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp('copy: source is not readable');
            return FALSE;
        }
        // source is symbolic link
        if (is_link($sourcePath)) {
            if (is_dir($destPath) && !is_link($destPath)) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("copy: unable to replace directory [$destPath] with file");
                return FALSE;
            }
            else {
                $destDirPath = dirname($destPath);
                if (is_dir($destDirPath)) {
                    $destDirWriteStatus = is_writable($destDirPath);
                }
                else {
                    self::setErrId(ERROR_NOT_FOUND);                    self::setErrExp("copy: directory [$destDirPath] was not found");
                    return FALSE;
                }
                if (!$destDirWriteStatus) {
                    self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("copy: destination directory [$destDirPath] is write-protected");
                    return FALSE;
                }
                else {
                    if (is_file($destPath) || is_link($destPath)) {
                        if (!$rewriteFiles) {
                            self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('copy: rewriting of files is not allowed');
                            return FALSE;
                        }
                        unlink($destPath);
                        $sourceLinkTarget = readlink($sourcePath);
                        symlink($sourceLinkTarget, $destPath);
                        return TRUE;
                    }
                    else {
                        $sourceLinkTarget = readlink($sourcePath);
                        symlink($sourceLinkTarget, $destPath);
                        return TRUE;
                    }
                }
            }
        }
        // source is file
        elseif (is_file($sourcePath)) {
            if (is_dir($destPath) && !is_link($destPath)) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("copy: unable to replace directory [$destPath] with file");
                return FALSE;
            }
            else {
                $destDirPath = dirname($destPath);
                if (is_dir($destDirPath)) {
                    $destDirWriteStatus = is_writable($destDirPath);
                }
                else {
                    self::setErrId(ERROR_NOT_FOUND);                    self::setErrExp("copy: directory [$destDirPath] was not found");
                    return FALSE;
                }
                if (is_link($destPath)) {
                    if (!$rewriteFiles) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp('copy: rewriting of files is not allowed');
                        return FALSE;
                    }
                    if (!$destDirWriteStatus) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("copy: destination directory [$destDirPath] is write-protected");
                        return FALSE;
                    }
                    unlink($destPath);
                    copy($sourcePath, $destPath);
                    return TRUE;
                }
                elseif (is_file($destPath)) {
                    $destFileWriteStatus = is_writable($destPath);
                    if (!$rewriteFiles) {
                        self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("copy: rewriting of files is not allowed");
                        return FALSE;
                    }
                    elseif ($destFileWriteStatus) {
                        copy($sourcePath, $destPath);
                        return TRUE;
                    }
                    elseif (!$destFileWriteStatus) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                        self::setErrExp("copy: destination file [$destPath] is write-protected");
                        return  FALSE;
                    }
                }
                else {
                    if (!$destDirWriteStatus) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("copy: destination directory [$destDirPath] is write-protected");
                        return FALSE;
                    }
                    copy($sourcePath, $destPath);
                    return TRUE;
                }
            }
        }
        // source is directory
        else {
            if (is_file($destPath)) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('copy: unable to replace file with directory');
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
                self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("copy: destination directory [$destPath] is write-protected");
                return FALSE;
            }
            elseif (!$mergeDirs) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp('copy: merging of directories is not allowed');
                return FALSE;
            }
            $sourceReal = realpath($sourcePath);
            $destReal = realpath($destPath);
            $sourceLen = strlen($sourceReal);
            $destLen = strlen($destReal);
            if (($sourceReal == $destReal) || (($sourceLen < $destLen) && ($sourceReal == substr($destReal, 0, $sourceLen)))) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("copy: unable to copy directory [$sourcePath] into itself");
                return FALSE;
            }
            $stack = array($sourcePath);
            $divPosition = strlen($sourcePath) + 1;
            while (count($stack)) {
                $sourceDirPath = array_pop($stack);
                $destDirPath = rtrim("$destPath/".substr($sourceDirPath, $divPosition), "/");
                $destDirWriteStatus = is_writable($destDirPath);
                $sourceDirContent = array_diff(scandir($sourceDirPath), array('.', '..'));
                foreach ($sourceDirContent as $sourceItemName) {
                    $sourceFullPath = "$sourceDirPath/$sourceItemName";
                    $destFullPath = "$destDirPath/$sourceItemName";
                    $sourceItemReadStatus = is_readable($sourceFullPath);
                    if (is_link($sourceFullPath)) {// link handling
                        if ($removeConflictFiles && is_dir($destFullPath) && !is_link($destFullPath)) {
                            if (!self::remove($destFullPath)) {
                                return FALSE;
                            }
                        }
                        if (is_dir($destFullPath) && !is_link($destFullPath) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("copy: unable to replace directory [$destFullPath] with link [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_file($destFullPath) || is_link($destFullPath)) {
                            if ($rewriteFiles && $destDirWriteStatus && $sourceItemReadStatus) {
                                unlink($destFullPath);
                                $sourceLinkTarget = readlink($sourceFullPath);
                                symlink($sourceLinkTarget, $destFullPath);
                            }
                            elseif ($rewriteFiles && !$sourceItemReadStatus && !$skipNotReadable) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                                self::setErrExp("copy: unable to read link [$sourceFullPath]");
                                return FALSE;
                            }
                            elseif ($rewriteFiles && !$destDirWriteStatus && !$skipWriteProtected) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                                    self::setErrExp("copy: directory [$destDirPath] is write-protected");
                                return FALSE;
                            }
                            elseif (!$skipExistentFiles) {
                                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("copy: file [$destFullPath] already exists");
                                return FALSE;
                            }
                        }
                        elseif (!is_dir($destFullPath) && $destDirWriteStatus && $sourceItemReadStatus) {
                            $sourceLinkTarget = readlink($sourceFullPath);
                            symlink($sourceLinkTarget, $destFullPath);
                        }
                        elseif (!$sourceItemReadStatus && !$skipNotReadable) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                                self::setErrExp("copy: unable to read link [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (!$destDirWriteStatus && !$skipWriteProtected) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                                self::setErrExp("copy: directory [$destDirPath] is write-protected");
                            return FALSE;
                        }
                    }
                    elseif (is_file($sourceFullPath)) { // file handling
                        if ($removeConflictFiles && is_dir($destFullPath) && !is_link($destFullPath)) {
                            if (!self::remove($destFullPath)) {
                                return FALSE;
                            }
                        }
                        if (is_dir($destFullPath) && !is_link($destFullPath) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("copy: unable to replace directory [$destFullPath] with file [$sourceFullPath]");
                            return FALSE;
                        }
                        //
                        elseif (is_link($destFullPath)) {
                            if ($rewriteFiles && $destDirWriteStatus && $sourceItemReadStatus) {
                                unlink($destFullPath);
                                $sourceLinkTarget = readlink($sourceFullPath);
                                symlink($sourceLinkTarget, $destFullPath);
                            }
                            elseif ($rewriteFiles && !$sourceItemReadStatus && !$skipNotReadable) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                                self::setErrExp("copy: unable to read file [$sourceFullPath]");
                                return FALSE;
                            }
                            elseif ($rewriteFiles && !$destDirWriteStatus && !$skipWriteProtected) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                                    self::setErrExp("copy: directory [$destDirPath] is write-protected");
                                return FALSE;
                            }
                            elseif (!$skipExistentFiles) {
                                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("copy: file [$destFullPath] already exists");
                                return FALSE;
                            }
                        }
                        elseif (is_file($destFullPath)) {
                            $destFileWriteStatus = is_writable($destFullPath);
                            if ($rewriteFiles && $sourceItemReadStatus && $destFileWriteStatus) {
                                copy($sourceFullPath, $destFullPath);
                            }
                            elseif ($rewriteFiles && !$sourceItemReadStatus && !$skipNotReadable) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                                self::setErrExp("copy: unable to read file [$sourceFullPath]");
                                return FALSE;
                            }
                            elseif ($rewriteFiles && !$destFileWriteStatus && !$skipWriteProtected) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                                    self::setErrExp("copy: file [$destFullPath] is write-protected");
                                return FALSE;
                            }
                            elseif (!$skipExistentFiles) {
                                self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("copy: file [$destFullPath] already exists");
                                return FALSE;
                            }
                        }
                        elseif (!is_dir($destFullPath) && $destDirWriteStatus && $sourceItemReadStatus) {
                            copy($sourceFullPath, $destFullPath);
                        }
                        elseif (!$destDirWriteStatus && !$skipWriteProtected) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                                self::setErrExp("copy: directory [$destDirPath] is write-protected");
                            return FALSE;
                        }
                        elseif (!$sourceItemReadStatus && !$skipNotReadable) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                            self::setErrExp("copy: unable to read file [$sourceFullPath]");
                            return FALSE;
                        }
                    }
                    elseif (is_dir($sourceFullPath)) { // directory handling
                        if ($removeConflictFiles && is_file($destFullPath)) {
                            if (!self::remove($destFullPath)) {
                                return FALSE;
                            }
                        }
                        $fileConflict = is_file($destFullPath);
                        if ($fileConflict && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("copy: unable to replace file [$destFullPath] with directory [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif ($sourceItemReadStatus && is_dir($destFullPath)) {
                            $stack[] = $sourceFullPath;
                        }
                        elseif ($sourceItemReadStatus && !is_dir($destFullPath) && !$fileConflict && $destDirWriteStatus) {
                            $stack[] = $sourceFullPath;
                            mkdir($destFullPath);
                        }
                        elseif (!$sourceItemReadStatus && !$skipNotReadable) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("copy: unable to read directory [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_dir($destFullPath) && !is_writable($destFullPath) && !$skipWriteProtected) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("copy: directory [$destFullPath] is write-protected");
                            return FALSE;
                        }
                        elseif (!$destDirWriteStatus && !$skipWriteProtected) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                            self::setErrExp("copy: directory [$destDirPath] is write-protected");
                            return FALSE;
                        }
                    }
                    elseif (!$skipNotReadable) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                        self::setErrExp("copy: directory [$sourceDirPath] lists files only");
                        return FALSE;
                    }
                    else {
                        $sourceDirContent = array();
                    }
                }
            }
            return TRUE;
        }
    }
    
    public static function move($sourcePath, $destPath, $mergeDirs = FALSE, $replaceFiles = FALSE, $skipExistentFiles = FALSE, $skipNotReadable = FALSE, $skipWriteProtected = FALSE, $skipConflicts = FALSE, $removeConflictFiles = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        $sourcePath = rtrim(preg_replace('#[/]+#', '/', $sourcePath), '/');
        $destPath = rtrim(preg_replace('#[/]+#', '/', $destPath), '/');
        if (!file_exists($sourcePath)) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp("move: source [$sourcePath] was not found");
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
                elseif (!is_dir($destDirPath)) {
                    self::setErrId(ERROR_NOT_FOUND);                    self::setErrExp("move: directory [$destDirPath] does not exist");
                    return FALSE;
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
            $sourceReal = realpath($sourcePath);
            $destReal = realpath($destPath);
            $sourceLen = strlen($sourceReal);
            $destLen = strlen($destReal);
            if (($sourceReal == $destReal) || (($sourceLen < $destLen) && ($sourceReal == substr($destReal, 0, $sourceLen)))) {
                self::setErrId(ERROR_NOT_EXECUTED);                self::setErrExp("move: unable to move directory [$sourcePath] into itself");
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
                        if (is_file($destFullPath) || is_link($destFullPath)) {
                            $destIsFile = 1;
                        }
                        else {
                            $destIsFile = 0;
                        }
                        if ($removeConflictFiles && is_dir($destFullPath) && !is_link($destFullPath)) {
                            if (!self::remove($destFullPath)) {
                                return FALSE;
                            }
                        }
                        if (is_dir($destFullPath) && !is_link($destFullPath) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("move: unable to replace directory [$destFullPath] with file [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (!$destIsFile) {
                            if (!@rename($sourceFullPath, $destFullPath) && !$skipNotReadable) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("move: unable to move file [$sourceFullPath]. Probably you tried to move not readable file to another disk partition.");
                                return FALSE;
                            }
                        }
                        elseif ($destIsFile && $replaceFiles) {
                            if (!@rename($sourceFullPath, $destFullPath) && !$skipNotReadable) {
                                self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("move: unable to move file [$sourceFullPath]. Probably you tried to move not readable file to another disk partition.");
                                return FALSE;
                            }
                        }
                        elseif ($destIsFile && !$skipExistentFiles) {
                            self::setErrId(ERROR_NOT_EXECUTED);                                self::setErrExp("move: file [$destFullPath] already exists");
                            return FALSE;
                        }
                    }
                    elseif (!$sourceIsNotDir) { // directory handling
                        if ($removeConflictFiles && is_file($destFullPath)) {
                            if (!self::remove($destFullPath)) {
                                return FALSE;
                            }
                        }
                        if (is_file($destFullPath) && !$skipConflicts) {
                            self::setErrId(ERROR_ALREADY_EXISTS);                self::setErrExp("move: unable to replace file [$destFullPath] with directory [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_readable($sourceFullPath) && is_dir($destFullPath)) {
                            $stack[] = $sourceFullPath;
                        }
                        elseif (is_readable($sourceFullPath) && !is_dir($destFullPath) && $destDirWriteStatus) {
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
                    self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("move: source directory [$dirPath] is not empty");
                    return  FALSE;
                }
                elseif (!$parentDirWriteStatus && !$skipWriteProtected) {
                    self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("move: unable to remove directory [$dirPath] because parent directory [$parentDir] is write-protected");
                    return  FALSE;
                }
                elseif (!$dirIsNotEmpty && $parentDirWriteStatus) {
                    rmdir($dirPath);
                }
            }
            return TRUE;
        }
    }
    
    public static function remove($sourcePath, $skipNotReadable = FALSE, $skipWriteProtected = FALSE, $skipConflicts = FALSE) {
        self::$errid = 0;        self::$errexp = '';
        $sourcePath = rtrim(preg_replace('#[/]+#', '/', $sourcePath), '/');
        if (!file_exists($sourcePath)) {
            self::setErrId(ERROR_NOT_FOUND);            self::setErrExp("remove: source [$sourcePath] was not found");
            return FALSE;
        }
        // source is file or symbolic link
        if (is_file($sourcePath) || is_link($sourcePath)) {
            $sourceDirPath = dirname($sourcePath);
            if (!is_writable($sourceDirPath)) {
                self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("remove: source directory [$sourceDirPath] is write-protected");
                return FALSE;
            }
            unlink($sourcePath);
            return TRUE;
        }
        // source is directory
        elseif (is_dir($sourcePath)) {
            if (!is_readable($sourcePath)) {
                self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("remove: source directory [$sourcePath] is not readable");
                return FALSE;
            }
            $stack = array($sourcePath);
            $divPosition = strlen($sourcePath) + 1;
            while (count($stack)) {
                $sourceDirPath = array_pop($stack);
                $sourceDirWriteStatus = is_writable($sourceDirPath);
                $dirTree[] = $sourceDirPath;
                foreach (array_diff(scandir($sourceDirPath), array('.', '..')) as $sourceItemName) {
                    $sourceFullPath = "$sourceDirPath/$sourceItemName";
                    if ((is_file($sourceFullPath) || is_link($sourceFullPath))) {
                        $sourceIsNotDir = 1;
                    }
                    else {
                        $sourceIsNotDir = 0;
                    }
                    // file handling
                    if ($sourceIsNotDir && $sourceDirWriteStatus) {
                        unlink($sourceFullPath);
                    }
                    // directory handling
                    elseif (!$sourceIsNotDir) {
                        if (is_readable($sourceFullPath)) {
                            $stack[] = $sourceFullPath;
                        }
                        elseif (!$skipNotReadable) {
                            self::setErrId(ERROR_RESTRICTED_ACCESS);                self::setErrExp("remove: unable to read directory [$sourceFullPath]");
                            return FALSE;
                        }
                    }
                    elseif (!$sourceDirWriteStatus && !$skipWriteProtected) {
                        self::setErrId(ERROR_RESTRICTED_ACCESS);                        self::setErrExp("remove: unable to remove file [$sourceFullPath] because source directory [$sourceDirPath] is write-protected");
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
                    self::setErrId(ERROR_NOT_EXECUTED);                    self::setErrExp("remove: source directory [$dirPath] is not empty");
                    return  FALSE;
                }
                elseif (!$parentDirWriteStatus && !$skipWriteProtected) {
                    self::setErrId(ERROR_RESTRICTED_ACCESS);                    self::setErrExp("remove: unable to remove directory [$dirPath] because parent directory [$parentDir] is write-protected");
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
