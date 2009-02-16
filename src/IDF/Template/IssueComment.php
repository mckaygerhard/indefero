<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');

/**
 * Make the links to issues and commits.
 */
class IDF_Template_IssueComment extends Pluf_Template_Tag
{
    private $project = null;
    private $request = null;
    private $scm = null;

    function start($text, $request, $echo=true, $wordwrap=true, $esc=true, $autolink=true, $nl2br=false)
    {
        $this->project = $request->project;
        $this->request = $request;
        $this->scm = IDF_Scm::get($request->project);
        if ($wordwrap) $text = wordwrap($text, 69, "\n", true);
        if ($esc) $text = Pluf_esc($text);
        if ($autolink) {
            $text = preg_replace('#([a-z]+://[^\s\(\)]+)#i',
                                 '<a href="\1">\1</a>', $text);
        }
        if ($request->rights['hasIssuesAccess']) {
            $text = preg_replace_callback('#(issues?|bugs?|tickets?)\s+(\d+)((\s+and|\s+or|,)\s+(\d+)){0,}#im',
                                          array($this, 'callbackIssues'), $text);
        }
        if ($request->rights['hasSourceAccess']) {
            $text = preg_replace_callback('#(commit\s+)([0-9a-f]{1,40})#im',
                                          array($this, 'callbackCommit'), $text);
            $text = preg_replace_callback('#(src:)([^\s\(\)]+)#im',
                                          array($this, 'callbackSource'), $text);
        }
        if ($nl2br) $text = nl2br($text);
        if ($echo) {
            echo $text;
        } else {
            return $text;
        }
    }

    /**
     * General call back for the issues.
     */
    function callbackIssues($m)
    {
        if (count($m) == 3) {
            $issue = new IDF_Issue($m[2]);
            if ($issue->id > 0 and $issue->project == $this->project->id) {
                return $this->linkIssue($issue, $m[1].' '.$m[2]);
            } else {
                return $m[0]; // not existing issue.
            }
        } else {
            return preg_replace_callback('/(\d+)/', 
                                         array($this, 'callbackIssue'), 
                                         $m[0]); 
        }
    }

    /**
     * Call back for the case of multiple issues like 'issues 1, 2 and 3'.
     *
     * Called from callbackIssues, it is linking only the number of
     * the issues.
     */
    function callbackIssue($m)
    {
        $issue = new IDF_Issue($m[1]);
        if ($issue->id > 0 and $issue->project == $this->project->id) {
            return $this->linkIssue($issue, $m[1]);
        } else {
            return $m[0]; // not existing issue.
        }
    }

    function callbackCommit($m)
    {
        if ($this->scm->testHash($m[2]) != 'commit') {
            return $m[0];
        }
        $co = $this->scm->getCommit($m[2]);
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Source::commit', array($this->project->shortname, $co->commit)).'">'.$m[1].$m[2].'</a>';
    }

    function callbackSource($m)
    {
        $branches = $this->scm->getBranches();
        if (count($branches) == 0) return $m[0];
        $file = $m[2];
        if ('commit' != $this->scm->testHash($branches[0], $file)) {
            return $m[0];
        }
        $request_file_info = $this->scm->getFileInfo($file, $branches[0]);
        if (!$request_file_info) {
            return $m[0];
        }
        if ($request_file_info->type != 'tree') {
            return $m[1].'<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Source::tree', array($this->project->shortname, $branches[0], $file)).'">'.$m[2].'</a>';
        }
        return $m[0];
    }

    /**
     * Generate the link to an issue.
     *
     * @param IDF_Issue Issue.
     * @param string Name of the link.
     * @return string Linked issue.
     */
    public function linkIssue($issue, $title)
    {
        $ic = (in_array($issue->status, $this->project->getTagIdsByStatus('closed'))) ? 'issue-c' : 'issue-o';
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view', 
                                                    array($this->project->shortname, $issue->id)).'" class="'.$ic.'" title="'.Pluf_esc($issue->summary).'">'.Pluf_esc($title).'</a>';
    }
}
