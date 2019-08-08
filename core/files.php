<?php

/*
 *     phpMeccano v0.2.0. Web-framework written with php programming language. Core module [files.php].
 *     Copyright (C) 2015-2019  Alexei Muzarov
 * 
 *     This program is free software; you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation; either version 2 of the License, or
 *     (at your option) any later version.
 * 
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 * 
 *     You should have received a copy of the GNU General Public License along
 *     with this program; if not, write to the Free Software Foundation, Inc.,
 *     51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 *     e-mail: azexmail@gmail.com
 *     e-mail: azexmail@mail.ru
 *     https://bitbucket.org/azexmail/phpmeccano
 */

namespace core;

require_once MECCANO_CORE_DIR.'/swconst.php';

interface intFiles {
    public static function errId();
    public static function errExp();
    public static function copy($sourcePath, $destPath, $mergeDirs = FALSE, $rewriteFiles = FALSE, $skipExistentFiles = FALSE, $skipNotReadable = FALSE, $skipWriteProtected = FALSE, $skipConflicts = FALSE, $removeConflictFiles = FALSE);
    public static function move($sourcePath, $destPath, $mergeDirs = FALSE, $replaceFiles = FALSE, $skipExistentFiles = FALSE, $skipNotReadable = FALSE, $skipWriteProtected = FALSE, $skipConflicts = FALSE, $removeConflictFiles = FALSE);
    public static function remove($sourcePath, $skipNotReadable = FALSE, $skipWriteProtected = FALSE, $skipConflicts = FALSE);
}

class Files implements intFiles {
    private static $errid = 0; // error's id
    private static $errexp = ''; // error's explanation
    
    private static function setError($id, $exp) {
        self::$errid = $id;
        self::$errexp = $exp;
        if (MECCANO_SHOW_ERRORS) {
            echo "<br/><span style='font-style: large; padding: 10px; background: yellow; display: inline-block; color: red'>ERROR $id<br/>$exp</span><br/>";
        }
    }
    
    private static function zeroizeError() {
        self::$errid = 0;        self::$errexp = '';
    }

    public static function errId() {
        return self::$errid;
    }
    
    public static function errExp() {
        return self::$errexp;
    }

    public static function copy($sourcePath, $destPath, $mergeDirs = FALSE, $rewriteFiles = FALSE, $skipExistentFiles = FALSE, $skipNotReadable = FALSE, $skipWriteProtected = FALSE, $skipConflicts = FALSE, $removeConflictFiles = FALSE) {
        self::zeroizeError();
        $sourcePath = rtrim(preg_replace('#[/]+#', '/', $sourcePath), '/');
        $destPath = rtrim(preg_replace('#[/]+#', '/', $destPath), '/');
        if (!file_exists($sourcePath)) {
            self::setError(ERROR_NOT_FOUND, 'copy: source was not found');
            return FALSE;
        }
        elseif (!is_readable($sourcePath)) {
            self::setError(ERROR_RESTRICTED_ACCESS, 'copy: source is not readable');
            return FALSE;
        }
        // source is symbolic link
        if (is_link($sourcePath)) {
            if (is_dir($destPath) && !is_link($destPath)) {
                self::setError(ERROR_NOT_EXECUTED, "copy: unable to replace directory [$destPath] with file");
                return FALSE;
            }
            else {
                $destDirPath = dirname($destPath);
                if (is_dir($destDirPath)) {
                    $destDirWriteStatus = is_writable($destDirPath);
                }
                else {
                    self::setError(ERROR_NOT_FOUND, "copy: directory [$destDirPath] was not found");
                    return FALSE;
                }
                if (!$destDirWriteStatus) {
                    self::setError(ERROR_RESTRICTED_ACCESS, "copy: destination directory [$destDirPath] is write-protected");
                    return FALSE;
                }
                else {
                    if (is_file($destPath) || is_link($destPath)) {
                        if (!$rewriteFiles) {
                            self::setError(ERROR_NOT_EXECUTED, 'copy: rewriting of files is not allowed');
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
                self::setError(ERROR_NOT_EXECUTED, "copy: unable to replace directory [$destPath] with file");
                return FALSE;
            }
            else {
                $destDirPath = dirname($destPath);
                if (is_dir($destDirPath)) {
                    $destDirWriteStatus = is_writable($destDirPath);
                }
                else {
                    self::setError(ERROR_NOT_FOUND, "copy: directory [$destDirPath] was not found");
                    return FALSE;
                }
                if (is_link($destPath)) {
                    if (!$rewriteFiles) {
                        self::setError(ERROR_NOT_EXECUTED, 'copy: rewriting of files is not allowed');
                        return FALSE;
                    }
                    if (!$destDirWriteStatus) {
                        self::setError(ERROR_RESTRICTED_ACCESS, "copy: destination directory [$destDirPath] is write-protected");
                        return FALSE;
                    }
                    unlink($destPath);
                    copy($sourcePath, $destPath);
                    return TRUE;
                }
                elseif (is_file($destPath)) {
                    $destFileWriteStatus = is_writable($destPath);
                    if (!$rewriteFiles) {
                        self::setError(ERROR_NOT_EXECUTED, "copy: rewriting of files is not allowed");
                        return FALSE;
                    }
                    elseif ($destFileWriteStatus) {
                        copy($sourcePath, $destPath);
                        return TRUE;
                    }
                    elseif (!$destFileWriteStatus) {
                        self::setError(ERROR_RESTRICTED_ACCESS, "copy: destination file [$destPath] is write-protected");
                        return  FALSE;
                    }
                }
                else {
                    if (!$destDirWriteStatus) {
                        self::setError(ERROR_RESTRICTED_ACCESS, "copy: destination directory [$destDirPath] is write-protected");
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
                self::setError(ERROR_NOT_EXECUTED, 'copy: unable to replace file with directory');
                return FALSE;
            }
            $destDirPath = dirname($destPath);
            if (!is_dir($destPath)) {
                if (is_writable($destDirPath)) {
                    mkdir($destPath);
                }
                else {
                    self::setError(ERROR_RESTRICTED_ACCESS, "copy: unable to create destination directory [$destPath]");
                    return FALSE;
                }
            }
            elseif (!is_writable($destPath)) {
                self::setError(ERROR_RESTRICTED_ACCESS, "copy: destination directory [$destPath] is write-protected");
                return FALSE;
            }
            elseif (!$mergeDirs) {
                self::setError(ERROR_NOT_EXECUTED, 'copy: merging of directories is not allowed');
                return FALSE;
            }
            $sourceReal = realpath($sourcePath);
            $destReal = realpath($destPath);
            $sourceLen = strlen($sourceReal);
            $destLen = strlen($destReal);
            if (($sourceReal == $destReal) || (($sourceLen < $destLen) && ($sourceReal == substr($destReal, 0, $sourceLen)))) {
                self::setError(ERROR_NOT_EXECUTED, "copy: unable to copy directory [$sourcePath] into itself");
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
                            self::setError(ERROR_ALREADY_EXISTS, "copy: unable to replace directory [$destFullPath] with link [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_file($destFullPath) || is_link($destFullPath)) {
                            if ($rewriteFiles && $destDirWriteStatus && $sourceItemReadStatus) {
                                unlink($destFullPath);
                                $sourceLinkTarget = readlink($sourceFullPath);
                                symlink($sourceLinkTarget, $destFullPath);
                            }
                            elseif ($rewriteFiles && !$sourceItemReadStatus && !$skipNotReadable) {
                                self::setError(ERROR_RESTRICTED_ACCESS, "copy: unable to read link [$sourceFullPath]");
                                return FALSE;
                            }
                            elseif ($rewriteFiles && !$destDirWriteStatus && !$skipWriteProtected) {
                                self::setError(ERROR_RESTRICTED_ACCESS, "copy: directory [$destDirPath] is write-protected");
                                return FALSE;
                            }
                            elseif (!$skipExistentFiles) {
                                self::setError(ERROR_NOT_EXECUTED, "copy: file [$destFullPath] already exists");
                                return FALSE;
                            }
                        }
                        elseif (!is_dir($destFullPath) && $destDirWriteStatus && $sourceItemReadStatus) {
                            $sourceLinkTarget = readlink($sourceFullPath);
                            symlink($sourceLinkTarget, $destFullPath);
                        }
                        elseif (!$sourceItemReadStatus && !$skipNotReadable) {
                            self::setError(ERROR_RESTRICTED_ACCESS, "copy: unable to read link [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (!$destDirWriteStatus && !$skipWriteProtected) {
                            self::setError(ERROR_RESTRICTED_ACCESS, "copy: directory [$destDirPath] is write-protected");
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
                            self::setError(ERROR_ALREADY_EXISTS, "copy: unable to replace directory [$destFullPath] with file [$sourceFullPath]");
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
                                self::setError(ERROR_RESTRICTED_ACCESS, "copy: unable to read file [$sourceFullPath]");
                                return FALSE;
                            }
                            elseif ($rewriteFiles && !$destDirWriteStatus && !$skipWriteProtected) {
                                self::setError(ERROR_RESTRICTED_ACCESS, "copy: directory [$destDirPath] is write-protected");
                                return FALSE;
                            }
                            elseif (!$skipExistentFiles) {
                                self::setError(ERROR_NOT_EXECUTED, "copy: file [$destFullPath] already exists");
                                return FALSE;
                            }
                        }
                        elseif (is_file($destFullPath)) {
                            $destFileWriteStatus = is_writable($destFullPath);
                            if ($rewriteFiles && $sourceItemReadStatus && $destFileWriteStatus) {
                                copy($sourceFullPath, $destFullPath);
                            }
                            elseif ($rewriteFiles && !$sourceItemReadStatus && !$skipNotReadable) {
                                self::setError(ERROR_RESTRICTED_ACCESS, "copy: unable to read file [$sourceFullPath]");
                                return FALSE;
                            }
                            elseif ($rewriteFiles && !$destFileWriteStatus && !$skipWriteProtected) {
                                self::setError(ERROR_RESTRICTED_ACCESS, "copy: file [$destFullPath] is write-protected");
                                return FALSE;
                            }
                            elseif (!$skipExistentFiles) {
                                self::setError(ERROR_NOT_EXECUTED, "copy: file [$destFullPath] already exists");
                                return FALSE;
                            }
                        }
                        elseif (!is_dir($destFullPath) && $destDirWriteStatus && $sourceItemReadStatus) {
                            copy($sourceFullPath, $destFullPath);
                        }
                        elseif (!$destDirWriteStatus && !$skipWriteProtected) {
                            self::setError(ERROR_RESTRICTED_ACCESS, "copy: directory [$destDirPath] is write-protected");
                            return FALSE;
                        }
                        elseif (!$sourceItemReadStatus && !$skipNotReadable) {
                            self::setError(ERROR_RESTRICTED_ACCESS, "copy: unable to read file [$sourceFullPath]");
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
                            self::setError(ERROR_ALREADY_EXISTS, "copy: unable to replace file [$destFullPath] with directory [$sourceFullPath]");
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
                            self::setError(ERROR_RESTRICTED_ACCESS, "copy: unable to read directory [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_dir($destFullPath) && !is_writable($destFullPath) && !$skipWriteProtected) {
                            self::setError(ERROR_RESTRICTED_ACCESS, "copy: directory [$destFullPath] is write-protected");
                            return FALSE;
                        }
                        elseif (!$destDirWriteStatus && !$skipWriteProtected) {
                            self::setError(ERROR_RESTRICTED_ACCESS, "copy: directory [$destDirPath] is write-protected");
                            return FALSE;
                        }
                    }
                    elseif (!$skipNotReadable) {
                        self::setError(ERROR_RESTRICTED_ACCESS, "copy: directory [$sourceDirPath] lists files only");
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
        self::zeroizeError();
        $sourcePath = rtrim(preg_replace('#[/]+#', '/', $sourcePath), '/');
        $destPath = rtrim(preg_replace('#[/]+#', '/', $destPath), '/');
        if (!file_exists($sourcePath)) {
            self::setError(ERROR_NOT_FOUND, "move: source [$sourcePath] was not found");
            return FALSE;
        }
        // source is file or symbolic link
        if (is_file($sourcePath) || is_link($sourcePath)) {
            if (is_dir($destPath)) {
                self::setError(ERROR_NOT_EXECUTED, "move: unable to replace directory [$destPath] with file");
                return FALSE;
            }
            else {
                $sourceDirPath = dirname($sourcePath);
                if (!is_writable($sourceDirPath)) {
                    self::setError(ERROR_RESTRICTED_ACCESS, "move: source directory [$sourceDirPath] is write-protected");
                    return FALSE;
                }
                $destDirPath = dirname($destPath);
                if (is_dir($destDirPath)) {
                    if (!is_writable($destDirPath)) {
                        self::setError(ERROR_RESTRICTED_ACCESS, "move: destination directory [$destDirPath] is write-protected");
                        return FALSE;
                    }
                }
                else {
                    self::setError(ERROR_NOT_FOUND, "move: directory [$destDirPath] was not found");
                    return FALSE;
                }
                if (is_file($destPath) || is_link($destPath)) {
                    if (!$replaceFiles) {
                        self::setError(ERROR_NOT_EXECUTED, 'move: replacing of files is not allowed');
                        return FALSE;
                    }
                }
                if (@rename($sourcePath, $destPath)) {
                    return TRUE;
                }
                else {
                    self::setError(ERROR_RESTRICTED_ACCESS, "move: unable to move file [$sourcePath]. Probably you tried to move not readable file to another disk partition.");
                    return FALSE;
                }
            }
        }
        // source is directory
        elseif (is_dir($sourcePath)) {
            if (!is_readable($sourcePath)) {
                self::setError(ERROR_RESTRICTED_ACCESS, "move: source directory [$sourcePath] is not readable");
                return FALSE;
            }
            if (is_file($destPath)) {
                self::setError(ERROR_NOT_EXECUTED, "move: unable to replace file [$destPath] with directory [$sourcePath]");
                return FALSE;
            }
            $destDirPath = dirname($destPath);
            if (!is_dir($destPath) && !is_link($destPath)) {
                if (is_writable($destDirPath)) {
                    mkdir($destPath);
                }
                elseif (!is_dir($destDirPath)) {
                    self::setError(ERROR_NOT_FOUND, "move: directory [$destDirPath] does not exist");
                    return FALSE;
                }
                else {
                    self::setError(ERROR_RESTRICTED_ACCESS, "move: destination directory [$destDirPath] is write-protected");
                    return FALSE;
                }
            }
            elseif (!$mergeDirs) {
                self::setError(ERROR_NOT_EXECUTED, "move: merging of directories is not allowed");
                return FALSE;
            }
            elseif (!is_writable($destPath)) {
                self::setError(ERROR_RESTRICTED_ACCESS, "move: destination directory [$destPath] is write-protected");
                return FALSE;
            }
            $sourceReal = realpath($sourcePath);
            $destReal = realpath($destPath);
            $sourceLen = strlen($sourceReal);
            $destLen = strlen($destReal);
            if (($sourceReal == $destReal) || (($sourceLen < $destLen) && ($sourceReal == substr($destReal, 0, $sourceLen)))) {
                self::setError(ERROR_NOT_EXECUTED, "move: unable to move directory [$sourcePath] into itself");
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
                            self::setError(ERROR_ALREADY_EXISTS, "move: unable to replace directory [$destFullPath] with file [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (!$destIsFile) {
                            if (!@rename($sourceFullPath, $destFullPath) && !$skipNotReadable) {
                                self::setError(ERROR_RESTRICTED_ACCESS, "move: unable to move file [$sourceFullPath]. Probably you tried to move not readable file to another disk partition.");
                                return FALSE;
                            }
                        }
                        elseif ($destIsFile && $replaceFiles) {
                            if (!@rename($sourceFullPath, $destFullPath) && !$skipNotReadable) {
                                self::setError(ERROR_RESTRICTED_ACCESS, "move: unable to move file [$sourceFullPath]. Probably you tried to move not readable file to another disk partition.");
                                return FALSE;
                            }
                        }
                        elseif ($destIsFile && !$skipExistentFiles) {
                            self::setError(ERROR_NOT_EXECUTED, "move: file [$destFullPath] already exists");
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
                            self::setError(ERROR_ALREADY_EXISTS, "move: unable to replace file [$destFullPath] with directory [$sourceFullPath]");
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
                            self::setError(ERROR_RESTRICTED_ACCESS, "move: unable to read directory [$sourceFullPath]");
                            return FALSE;
                        }
                        elseif (is_dir($destFullPath) && !is_writable($destFullPath) && !$skipWriteProtected) {
                            self::setError(ERROR_RESTRICTED_ACCESS, "move: unable to write into [$destFullPath]");
                            return FALSE;
                        }
                    }
                    elseif (!$sourceDirWriteStatus && !$skipWriteProtected) {
                        self::setError(ERROR_RESTRICTED_ACCESS, "move: unable to move file [$sourceFullPath] because source directory [$sourceDirPath] is write-protected");
                        return FALSE;
                    }
                    elseif (!$destDirWriteStatus && !$skipWriteProtected) {
                        self::setError(ERROR_RESTRICTED_ACCESS, "move: unable to move file [$sourceFullPath] because destination directory [$destDirPath] is write-protected");
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
                    self::setError(ERROR_NOT_EXECUTED, "move: source directory [$dirPath] is not empty");
                    return  FALSE;
                }
                elseif (!$parentDirWriteStatus && !$skipWriteProtected) {
                    self::setError(ERROR_RESTRICTED_ACCESS, "move: unable to remove directory [$dirPath] because parent directory [$parentDir] is write-protected");
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
        self::zeroizeError();
        $sourcePath = rtrim(preg_replace('#[/]+#', '/', $sourcePath), '/');
        if (!file_exists($sourcePath)) {
            self::setError(ERROR_NOT_FOUND, "remove: source [$sourcePath] was not found");
            return FALSE;
        }
        // source is file or symbolic link
        if (is_file($sourcePath) || is_link($sourcePath)) {
            $sourceDirPath = dirname($sourcePath);
            if (!is_writable($sourceDirPath)) {
                self::setError(ERROR_RESTRICTED_ACCESS, "remove: source directory [$sourceDirPath] is write-protected");
                return FALSE;
            }
            unlink($sourcePath);
            return TRUE;
        }
        // source is directory
        elseif (is_dir($sourcePath)) {
            if (!is_readable($sourcePath)) {
                self::setError(ERROR_RESTRICTED_ACCESS, "remove: source directory [$sourcePath] is not readable");
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
                            self::setError(ERROR_RESTRICTED_ACCESS, "remove: unable to read directory [$sourceFullPath]");
                            return FALSE;
                        }
                    }
                    elseif (!$sourceDirWriteStatus && !$skipWriteProtected) {
                        self::setError(ERROR_RESTRICTED_ACCESS, "remove: unable to remove file [$sourceFullPath] because source directory [$sourceDirPath] is write-protected");
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
                    self::setError(ERROR_NOT_EXECUTED, "remove: source directory [$dirPath] is not empty");
                    return  FALSE;
                }
                elseif (!$parentDirWriteStatus && !$skipWriteProtected) {
                    self::setError(ERROR_RESTRICTED_ACCESS, "remove: unable to remove directory [$dirPath] because parent directory [$parentDir] is write-protected");
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
