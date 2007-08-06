<?php


/**
 * Framework_User_VegaDNS 
 * 
 * @package VegaDNS
 * @copyright 2007 Bill Shupp
 * @author Bill Shupp <hostmaster@shupp.org> 
 * @license GPL 2.0  {@link http://www.gnu.org/licenses/gpl.txt}
 */
class Framework_User_VegaDNS extends Framework_User {

    /**
     * groups 
     * 
     * An array of the logged in users groups
     * 
     * @var mixed
     * @access public
     */
    public $groups = null;
    static public $permFlagValues = array(
            'inherit_group_perms'   => 0x01,
            'account_edit'          => 0x02,
            'account_create'        => 0x04,
            'account_delete'        => 0x08,
            'group_edit'            => 0x010,
            'group_create'          => 0x020,
            'group_delete'          => 0x040,
            'domain_edit'           => 0x080,
            'domain_create'         => 0x0100,
            'domain_delegate'       => 0x0200,
            'domain_delete'         => 0x0400,
            'record_edit'           => 0x0800,
            'record_create'         => 0x01000,
            'record_delete'         => 0x02000,
            'record_delegate'       => 0x04000,
            'default_record_edit'   => 0x08000,
            'default_record_create' => 0x010000,
            'default_record_delete' => 0x020000,
            'rrtype_allow_n'        => 0x040000,
            'rrtype_allow_a'        => 0x080000,
            'rrtype_allow_3'        => 0x0100000,
            'rrtype_allow_6'        => 0x0200000,
            'rrtype_allow_m'        => 0x0400000,
            'rrtype_allow_p'        => 0x0800000,
            'rrtype_allow_t'        => 0x01000000,
            'rrtype_allow_v'        => 0x02000000,
            'rrtype_allow_all'      => 0x04000000
            );

    /**
     * seniorPerms 
     * 
     * All permissions but inherit
     * 
     * @var float
     * @access private
     */
    private $seniorPerms = 134217726; // All but inherit_group_permissions
    /**
     * defaultPerms 
     * 
     * @var float
     * @access private
     */
    private $defaultPerms = 134217615; // All but account/group create/delete/edit

    public function authenticate($email, $password)
    {
        $sql = "SELECT user_id FROM `accounts` WHERE email=" . $this->db->Quote($email) . " AND password = MD5(" . $this->db->Quote($password) . ")";
        try {
            $result = $this->db->Execute($sql);
        } catch (Exception $e) {
            throw new Framework_Exception($e->getMessage());
        }
        if ($result->RecordCount() == 0) {
            return false;
        }
        $this->data = $result->FetchRow();
        $session = & Framework_Session::singleton();
        $session->__set((string)Framework::$site->config->user->userField, 
                $this->data[(string)Framework::$site->config->user->userField]);
        return true;
    }

    public function isSeniorAdmin($data = null)
    {
        if (is_null($data)) {
            $data = $this->data;
        }
        if ($data['account_type'] == 'senior_admin') {
            return true;
        }
        return false;
    }

    public function myGroupID()
    {
        return $this->data['group_id'];
    }

    // Get current account settings
    function getAccountInfo($userID) {

        $sql = "SELECT a.*, b.perm_value AS user_perms, c.perm_value AS group_perms
                FROM " . (string)Framework::$site->config->user->userTable . " a 
                LEFT JOIN user_permissions b ON a.user_id = b.user_id
                LEFT JOIN group_permissions c on a.group_id = c.group_id
                WHERE a." . (string)Framework::$site->config->user->userField 
                . " = " . $this->db->Quote($userID);

        try {
            $result = $this->db->Execute($sql);
        } catch (Exception $e) {
            throw new Framework_Exception($e->getMessage());
        }
        
        if ($result->RecordCount() == 0) {
            return NULL;
        }
        return $result->FetchRow();
    }

    function getSubGroups($id) {
        $q = "SELECT group_id from GROUPS WHERE group_id != ".$this->db->Quote($id)." AND parent_group_id = ".$this->db->Quote($id);
        $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
        if ($result->RecordCount() == 0) {
            return NULL;
        } else {
            $count = 0;
            while (!$result->EOF) {
                $row[$count] = $result->FetchRow();
                $count++;
            }
            return $row;
        }
    }

    function returnGroup($id, $g = NULL) {
        if ($g == NULL) {
            $g = $this->groups;
        }
        if ($g['group_id'] == $id) {
            return $g;
        }
        if (!isset($g['subgroups'])) {
            $array = NULL;
        } else {
            while (list($key,$val) = each($g['subgroups'])) {
                $temp = $this->returnGroup($id, $val);
                if ($temp['group_id'] == $id) {
                    $array = $temp;
                    break;
                } else {
                    $array = NULL;
                }
            }
        }
        return $array;
    }

    function getAllSubgroups($id) {
        // Get Top
        $q = "SELECT * FROM groups WHERE group_id=".$this->db->Quote($id)." LIMIT 1";
        $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
        if ($result->RecordCount() == 0) {
            return NULL;
        } else {
            $top = $result->FetchRow();
        }
        // Get subgroups
        $subs = $this->getSubGroups($top['group_id']);
        if ($subs == NULL) {
            return $top;
        } else {
            $count = 0;
            while (list($key,$val) = each($subs)) {
                $top['subgroups'][$count] = $this->getAllSubgroups($val['group_id']);
                $count++;
            }
            return $top;
        }
        
    }

    function getMenuTree($g,$top = NULL)
    {
        $groupstring = '';
        if (!is_null($g)) {
            $groupstring = "&group={$g['group_id']}";
        }
        if (!is_null($top)) {
            $name = 'Home';
            $topimage = 'home.png';
            $out .= '<img src=images/home.jpg> ';
        } else {
            $name = $g['name'];
            $topimage = 'group.gif';
            $out .= '<img src=images/group.gif> ';
        }

        $out = '';
        $out .= "<ul class='top'>\n";
        $out .= "<li><img src='images/$topimage' border='0'/> <a href=\"./?&module=Groups$groupstring\">$name</li>\n";
        $out .= "<li><img src='images/newfolder.png' border='0'/> <a href=\"./?module=Domains$groupstring\">Domains</li>\n";
        $out .= "<li><img src='images/user_folder.png' border='0'/> <a href=\"./?module=Users$groupstring\">Users</li>\n";
        $out .= "<li><img src='images/newfolder.png' border='0'/> <a href=\"./?module=Log$groupstring\">Log</li>\n";
        if (isset($g['subgroups'])) {
            $out .= "<li>\n";
            $out .= $this->getMenuTree($g['subgroups']);
            $out .= "<li>\n";
        }
        $out .= "</ul>\n";
        return $out;
    }

    function oldgetMenuRows($g,$top,$final_sub,$parent_subs) {
        static $indent = 0;
        static $parent_subs_count = 0;

        $out = '';
        if (isset($_SESSION['expanded'])) {
            $expanded = $_SESSION['expanded'];
        } else {
            $expanded = '';
        }

        // Figure out expansion stuff
        if ($top == NULL && $expanded != NULL) {
            $ex = explode(',', $expanded);
            while (list($key,$val) = each($ex)) {
                if ($g['group_id'] == $val) {
                    $is_expanded = 1;
                    $expand_image = 'dirtree_minus_';
                    $new_expand = array_trim(explode(',', $expanded), $val);
                    $expand_url = '&expanded='.implode(',', $new_expand);
                    break;
                } else {
                    $is_expanded = 0;
                    $expand_image = 'dirtree_plus_';
                    $expand_url = "&expanded=$expanded,".$g['group_id'];
                }
            }
        } else {
            if ($top != NULL) {
                $is_expanded = 1;
            } else {
                $is_expanded = 0;
            }
            $expand_image = 'dirtree_plus_';
            $expand_url = '&expanded='.$g['group_id'];
        }

        // Figure out subgroups and bottom tree image shape
        if (isset($g['subgroups'])) {
            // echo "FINAL SUB: $final_sub";
            $do_subgroups = 1;
            // $h_bottom_image = 'tee';
            $log_suffix = 'tee';
            if ($is_expanded == 1) {
                $h_bottom_image = 'tee';
                if ($final_sub != NULL && !$top) {
                    $log_suffix = 'elbow';
                }
            } else if ($final_sub == NULL){
                $h_bottom_image = 'tee';
            } else {
                $h_bottom_image = 'elbow';
            }
        } else {
            if ($is_expanded == 1) {
                $h_bottom_image = 'tee';
                if ($final_sub == NULL) {
                    $log_suffix = 'tee';
                } else {
                    $log_suffix = 'elbow';
                }
            } else if ($final_sub == NULL) {
                $h_bottom_image = 'tee';
                $log_suffix = 'tee';
            } else {
                $h_bottom_image = 'elbow';
                $log_suffix = 'elbow';
            }
            $do_subgroups = 0;
        }

        // Setup URLs
        $homeurl = "<a href=\"./?&mode=groups&group=".$g['group_id']."\">";
        $plusminusurl = "<a href=\"$base_url$expand_url&group=".$_SESSION['group'];
        // Retain current mode/domain/record
        if (isset($_REQUEST['mode'])) $plusminusurl .= "&mode=".$_REQUEST['mode'];
        if (isset($_REQUEST['domain'])) $plusminusurl .= "&domain=".$_REQUEST['domain'];
        if (isset($_REQUEST['record_id'])) $plusminusurl .= "&record_id=".$_REQUEST['record_id'];
        if (isset($_REQUEST['record_mode'])) $plusminusurl .= "&record_mode=".$_REQUEST['record_mode'];
        if (isset($_REQUEST['domain_mode'])) $plusminusurl .= "&domain_mode=".$_REQUEST['domain_mode'];

        $plusminusurl .= "\">";
        $groupurl = "<a href=\"$base_url&mode=groups&group=".$g['group_id']."\">";
        $domainsurl = "<a href=\"$base_url&mode=domains&group=".$g['group_id']."\">";
        $usersurl = "<a href=\"$base_url&mode=users&group=".$g['group_id']."\">";
        $logurl = "<a href=\"$base_url&mode=log&group=".$g['group_id']."\">";

        // Padding for vertical bar
        $count = 1;
        $padding = '';
        while ($count < $indent) {
            if ($parent_subs_count < $parent_subs) {
                $padding .= "<td><img src=\"images/dirtree_vertical.gif\" border=\"0\"></td>";
            } else {
                $padding .= "<td><img src=\"images/transparent.gif\" height=\"17\" width=\"17\" border=\"0\"></td>";
            }
            $count++;
        }

        // Figure out which item is highlighted (active)
        $groupsbg = '';
        $domainsbg = '';
        $usersbg = '';
        $logbg = '';
        if (isset($_SESSION['group']) && $_SESSION['group'] == $g['group_id']) {
            if (isset($_REQUEST['mode'])) {
                if ($_REQUEST['mode'] == 'domains' || $_REQUEST['mode'] == 'records') {
                    $domainsbg = ' class="white"';
                } else if ($_REQUEST['mode'] == 'users') {
                    $usersbg = ' class="white"';
                } else if ($_REQUEST['mode'] == 'log') {
                    $logbg = ' class="white"';
                } else if ($_REQUEST['mode'] == 'groups') {
                    $groupsbg = ' class="white"';
                }
            } else {
                    $groupsbg = ' class="white"';
            }
        } else if ($top != NULL) {
            if (!isset($_SESSION['group']) || $_SESSION['group'] == 'NULL') 
                $groupsbg = ' class="white"';
        }

        if ($top != NULL) {
            $out .= "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>$padding<td><img border=\"0\" alt=\"home\" src=\"images/home.png\"></td><td$groupsbg>$homeurl".$g['name']."</a></td></tr></table>\n";
        } else {
            $out .= "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>$padding<td>$plusminusurl<img border=\"0\" alt=\"home\" src=\"images/$expand_image$h_bottom_image.gif\"><td>$groupurl<img border=\"0\" alt=\"home\" src=\"images/group.gif\"></a></td><td$groupsbg>$groupurl".$g['name']."</a></td></tr></table>\n";
        }


        if ($top != NULL || $is_expanded) {
            $out .= "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>$padding<td><img border=\"0\" src=\"images/dirtree_tee.gif\"><td><img border=\"0\" src=\"images/newfolder.png\"></td><td$domainsbg>".$domainsurl."Domains</a></td></tr></table>\n";
            $out .= "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>$padding<td><img border=\"0\" src=\"images/dirtree_tee.gif\"></td><td><img border=\"0\" src=\"images/user_folder.png\"></td><td$usersbg>".$usersurl."Users</a></td></tr></table>\n";
            $out .= "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>$padding<td><img border=\"0\" src=\"images/dirtree_$log_suffix.gif\"></td><td><img border=\"0\" src=\"images/newfolder.png\"></td><td$logbg>".$logurl."Log</a></td></tr></table>\n";
        }

        $indent++;
        if ($is_expanded == 1) {
            $counter = 0;
            $last_sub = NULL;
            while ($do_subgroups == 1 && list($key,$val) = each($g['subgroups'])) {
                if ($top != NULL) $parent_subs_count++;
                $counter++;
                $out .=  "<!-- COUNTER: $counter COUNT: ".count($g['subgroups'])." -->\n";
                if ($counter == count($g['subgroups'])) $last_sub = 1;
                $out .= $this->getMenuRows($val,NULL,$last_sub);
            }
        }
        $indent--;
        return $out;
    }

    function isMyGroup($g) {
        if (($temp = $this->returnGroup($g)) == NULL) {
            return NULL;
        } else {
            return $temp;
        }
    }

    function isMyAccount($id) {

        // Fetch group_id
        if (($g = $this->userID_to_GroupID($id)) == NULL) {
            return FALSE;
        } else if (($temp = $this->returnGroup($g)) == NULL) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    function returnUserPermissions($id) {
        $q = "select * from user_permissions where user_id=".$this->db->Quote($id);
        $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
        if ($result->RecordCount() == 0) {
            return 'INHERIT';
        }
        $perms = $result->FetchRow();
        if ($perms['inherit_group_perms'] == 1) {
            return 'INHERIT';
        } else {
            return $perms;
        }
        
    }

    function returnGroupParentID($id) {
        $q = "select parent_group_id from groups where group_id=".$this->db->Quote($id);
        $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
        if ($result->RecordCount() == 0) return NULL;
        $row = $result->FetchRow();
        return $row['parend_group_id'];
    }

    function returnGroupPermissions($id) {
        $q = "select * from group_permissions where group_id=".$this->db->Quote($id);
        $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
        if ($result->RecordCount() == 0) return NULL;
        $perms = $result->FetchRow();
        if ($perms['inherit_group_perms'] == 1) {
            // Find the parent permissions
            $inherit = TRUE;
            while ($inherit != FALSE) {
                // Get parent ID
                $parent = $this->returnParentGroupID($id);
                $q = "select * from group_permissions where group_id=".$this->db->Quote($parent);
                $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
                if ($result->RecordCount() == 0) return NULL;
                $perms = $result->FetchRow();
                if ($perms['inherit_group_perms'] == 1) {
                    $id = $parent;
                    continue;
                } else {
                    $inherit = FALSE;
                }
            }
        }
        return $perms;
    }

    function returnSubgroupsQuery($g,$string) {

        if ($string == NULL) {
            $string = " group_id='".$g['group_id']."'";
        } else {
            $string .= " or group_id='".$g['group_id']."'";
        }

        if (!isset($g['subgroups'])) {
            return $string;
        } else {
            $temp = " ";
            while (list($key,$val) = each($g['subgroups'])) {
                $temp .= $this->returnSubgroupsQuery($val, $temp);
            }
        }
        return $string.$temp;
    }

    function canCreateSubGroups() {
        if ($this->account['permissions']['group_create'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canCreateDomains() {
        if ($this->account['permissions']['domain_create'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canEditDomains() {
        if ($this->account['permissions']['domain_edit'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canDeleteDomains() {
        if ($this->account['permissions']['domain_delete'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canCreateRecord() {
        if ($this->account['permissions']['record_create'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canDeleteRecord() {
        if ($this->account['permissions']['record_delete'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canEditRecord() {
        if ($this->account['permissions']['record_edit'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canDelegateRecord() {
        if ($this->account['permissions']['record_delegate'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canCreateDefaultRecords() {
        if ($this->account['permissions']['default_record_create'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canEditDefaultRecords() {
        if ($this->account['permissions']['default_record_edit'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canDeleteDefaultRecords() {
        if ($this->account['permissions']['default_record_delete'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canEditUser($id) {
        if ($this->account['permissions']['account_edit'] == 1) {
            if ($this->isMyAccount($id)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    function canEditSelf() {
        if ($this->account['permissions']['self_edit'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function userID_to_GroupID($user_id) {
        $q = "select group_id from accounts where user_id=".$this->db->Quote($user_id);
        $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
        if ($result->RecordCount() == 0) {
            return NULL;
        }

        $row = $result->FetchRow();
        return $row['group_id'];
    }

    function canEditSubGroups() {
        if ($this->account['permissions']['group_edit'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canDeleteGroup($g) {
        if ($this->account['permissions']['group_delete'] == 1) {
            if ($g == NULL) {
                return TRUE;
            } else if ($this->isMyGroup($g)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    function canEditGroup($g) {
        if ($this->account['permissions']['group_edit'] == 1) {
            if ($g == NULL) {
                return TRUE;
            } else if ($this->isMyGroup($g)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }


    function canCreateUsers($id,$g) {

        // Senior Admins can do anything
        if ($this->account['account_type'] == 'senior_admin') return TRUE;

        // See if it's the logged in user
        if ($id == NULL) {
            if ($this->account['permissions']['account_create'] == 1) {
                return TRUE;
            } else {
                return FALSE;
            }
        }

        // Else look up the permissions

        $perms = $this->returnUserPermissions($id);
        if ($perms == "INHERIT") {
            // GET GROUP PERMS
            $perms = $this->returnGroupPermissions($g);
        } else if ($perms == NULL) {
            return FALSE;
        } else if ($perms['group_create'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canDeleteUsers($id,$g) {

        // Senior Admins can do anything
        if ($this->account['account_type'] == 'senior_admin') return TRUE;

        // See if it's the logged in user
        if ($id == NULL) {
            if ($this->account['permissions']['account_delete'] == 1) {
                return TRUE;
            } else {
                return FALSE;
            }
        }

        // Else look up the permissions

        $perms = $this->returnUserPermissions($id);
        if ($perms == "INHERIT") {
            // GET GROUP PERMS
            $perms = $this->returnGroupPermissions($g);
        } else if ($perms == NULL) {
            return FALSE;
        } else if ($perms['group_create'] == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function canDeleteUser($id) {

        if ($this->canDeleteUsers(NULL,NULL) == FALSE) {
            return FALSE;
        } else {
            $q = "select group_id from accounts where user_id='$id'";
            $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
            if ($result->RecordCount() == 0) return NULL;
            $row = $result->FetchRow();
            if ($this->isMyGroup($row['group_id']) != NULL) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
    }

    function returnGroupID($name) {
        $q = "select group_id from groups where name=".$this->db->Quote($name);
        $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
        if ($result->RecordCount() == 0) return NULL;
        $row = $result->FetchRow();
        return $row['group_id'];
    }

    function returnUserID($email) {
        $q = "select user_id from accounts where email=".$this->db->Quote($email);
        $result = $this->db->Execute($q) or die($this->db->ErrorMsg());
        if ($result->RecordCount() == 0) return NULL;
        $row = $result->FetchRow();
        return $row['user_id'];
    }


    function returnCreateGroupPermQuery($name) {
        // Get permissions key list from senior_perms array
        // Then compare user perms against $_REQUEST elements 

        $u_perms = $this->account['permissions'];
        $perm_array = array();

        while (list($key,$val) = each($this->senior_perms)) {
            if (isset($u_perms[$key]) && $u_perms[$key] == 1) {
                if (isset($_REQUEST[$key])) {
                    $perm_array[$key] = 1;
                } else {
                    $perm_array[$key] = 0;
                }
            }
        }

        // Now that the perm_array is built, let's build the query string
        if (($id = $this->returnGroupID($name)) == NULL) return NULL;


        // Build colmns, values
        $col_string = "";
        $val_string = "";
        while (list($key,$val) = each($perm_array)) {
            $col_string .= ",$key";
            $val_string .= ",$val";
        }
        $q = "insert into group_permissions (group_id$col_string) values('$id'$val_string)" ;

        // INHERIT???

        return $q;
    }

    function returnEditGroupPermQuery($id) {

        // Get permissions key list from senior_perms array
        // Then compare user perms against $_REQUEST elements 

        $u_perms = $this->account['permissions'];
        $perm_array = array();

        while (list($key,$val) = each($this->senior_perms)) {
            if (isset($u_perms[$key]) && $u_perms[$key] == 1) {
                if (isset($_REQUEST[$key])) {
                    $perm_array[$key] = 1;
                } else {
                    $perm_array[$key] = 0;
                }
            }
        }

        // Build set string
        $edit_string = "";
        $counter = 0;
        while (list($key,$val) = each($perm_array)) {
            $edit_string .= " $key=".$this->db->Quote($val);
            $counter++;
            if ($counter < count($perm_array)) $edit_string .= ",";
        }
        $q = "update group_permissions set $edit_string where group_id='$id'";

        return $q;
    }

    function returnEditAccountPermQuery($id,$inherit) {
        // If we are inheriting, just set that
        if ($inherit != NULL) {
            $q = "update user_permissions set inherit_group_perms = 1  where user_id='$id'";
            return $q;
        }

        // Otherwise get permissions key list from default_perms array
        // Then compare user perms against $_REQUEST elements 

        $perm_array = array();

        while (list($key,$val) = each($default_perms)) {
            if (isset($_REQUEST[$key])) {
                $perm_array[$key] = 1;
            } else {
                $perm_array[$key] = 0;
            }
        }

        // Build set string
        $edit_string = " inherit_group_perms = 0, ";
        $counter = 0;
        while (list($key,$val) = each($perm_array)) {
            $edit_string .= " $key=".$this->db->Quote($val);
            $counter++;
            if ($counter < count($perm_array)) $edit_string .= ",";
        }
        $q = "update user_permissions set $edit_string where user_id='$id'";

        return $q;
    }

    function returnCreateUserPermQuery($email) {
        // Get permissions key list from senior_perms array
        // Then compare user perms against $_REQUEST elements 

        $u_perms = $this->account['permissions'];
        $perm_array = array();

        while (list($key,$val) = each($this->senior_perms)) {
            if (isset($u_perms[$key]) && $u_perms[$key] == 1) {
                if (isset($_REQUEST[$key])) {
                    $perm_array[$key] = 1;
                } else {
                    $perm_array[$key] = 0;
                }
            }
        }

        // Now that the perm_array is built, let's build the query string
        if (($id = $this->returnUserID($email)) == NULL) return NULL;


        // Build colmns, values
        $col_string = "";
        $val_string = "";
        while (list($key,$val) = each($perm_array)) {
            $col_string .= ",$key";
            $val_string .= ",$val";
        }
        $q = "insert into user_permissions (user_id$col_string) values('$id'$val_string)" ;

        // INHERIT???

        return $q;
    }

    protected function getUserData($userID) 
    {
        if (is_null($userID)) {
            $session = & Framework_Session::singleton();
            $userID = $session->{Framework::$site->config->user->userField};
            if (is_null($userID)) {
                $userID = (string)Framework::$site->config->user->defaultUser;
            } else {
                $userID = $session->{(string)Framework::$site->config->user->userField};
            }
        }

        $result = $this->getAccountInfo($userID);
        if ($result == NULL) {
            throw new Framework_Exception("Could not look up " . (string)Framework::$site->config->user->userField);
        }
        $this->data = $result;
        $this->data['permissions'] = $this->getPerms();
        $this->groups = $this->getAllSubGroups($this->data['group_id']);
    }

    /**
     * getBit 
     * 
     * Get bit value
     * 
     * @param mixed $bitmap 
     * @param mixed $bit 
     * @access public
     * @return bool $bit
     */
    public function getBit($bitmap, $bit)
    {
        if (!isset($this->permFlagValues[$bit])) {
            throw new Framework_Exception("Error - unknown bit value specified: $bit");
        }
        $bitValue = $this->permFlagValues[$bit];
        return ($bitmap&$bitValue) ? true : false;
    }

    /**
     * setBit 
     * 
     * Set bit flag.
     * 
     * @param mixed $bitmap 
     * @param mixed $bit 
     * @param bool $value 
     * @access public
     * @return void
     * @throws Framework_Exception if $bit is unknown
     * @see getBit()
     */
    public function setBit(&$bitmap, $bit, $value)
    {
        if (!isset($this->permFlagValues[$bit])) {
            throw new Framework_Exception("Unknown GID Bit value specified. $bit");
        }
        if (!is_bool($value)) {
            throw new Framework_Exception('Non-boolean value specified: ' . var_dump($value));
        }
        $bitValue = $this->permFlagValues[$bit];
        $value = ($value == true) ? $bitValue : 0;
        $bitmap = (int)$value|(~(int)$bitValue&(int)$bitmap);
    }

    public function getPerms($account = null)
    {
        if (is_null($account)) {
            $account = $this->data;
        }
        if ($this->data['account_type'] == 'senior_admin') {
            return $this->seniorPerms;
        }
        if ($account['user_perms'] == NULL || 
            $this->getBit($this->data['user_perms'], 'inherit_group_permissions')) {
            if($account['group_perms'] == NULL) {
                return $this->defaultPerms;
            }
            return $account['group_perms'];
        }
        return $account['user_perms'];
    }

};
?>