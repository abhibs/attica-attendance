<?php
include("dbConnection.php");

$pendingMessages = 0;
$releaseChatUnreadCount = 0;
$username = $_SESSION['employeeId'] ?? '';
$branchId  = $_SESSION['branchCode'] ?? '';
$attendanceBranchId = trim((string) ($_SESSION['branchCode'] ?? ''));
$attendanceUrl = 'https://atticagold.app/?fresh_login=1';
$vmAttendanceUrl = 'https://atticagold.app/admin/vm-login';
$vmAssignedBranches = $_SESSION['vmAssignedBranches']
    ?? $_SESSION['vm_assigned_branches']
    ?? $_SESSION['assignedBranches']
    ?? $_SESSION['assigned_branches']
    ?? $_SESSION['branchCode']
    ?? '';

if (is_array($vmAssignedBranches)) {
    $vmAssignedBranches = implode(',', $vmAssignedBranches);
}

$vmAssignedBranches = trim((string) $vmAssignedBranches);
$vmLoginPassword = getenv('VM_LOGIN_PASSWORD') ?: 'vm-login';

if ($attendanceBranchId !== '') {
    $attendanceUrl .= '&branch_id=' . rawurlencode($attendanceBranchId);
}

$allowed = ['AGPL216', 'AGPL022','AGPL030'];
if ($branchId !== '') {

    $branchId = mysqli_real_escape_string($con, $branchId);

    $sql = "
        SELECT COUNT(*) AS cnt
        FROM branch_vm_chat
        WHERE branchId = '$branchId'
          AND sender_type = 'VM'
          AND seen_branch = 0
    ";

    $res = mysqli_query($con, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $pendingMessages = (int)$row['cnt'];
    }

    $releaseBranchId = $_SESSION['branchCode'] ?? '';
    $releaseIds = [$releaseBranchId];
    $releaseBranchIdEsc = mysqli_real_escape_string($con, $releaseBranchId);
    $releaseBranchRes = mysqli_query(
        $con,
        "SELECT b.branchId, b.branchName, u.username
         FROM branch b
         LEFT JOIN users u ON u.branch = b.branchId OR u.username = b.branchId
         WHERE b.branchId = '$releaseBranchIdEsc'
         LIMIT 20"
    );

    while ($releaseBranchRes && ($releaseRow = mysqli_fetch_assoc($releaseBranchRes))) {
        $releaseIds[] = $releaseRow['branchId'] ?? '';
        $releaseIds[] = $releaseRow['branchName'] ?? '';
        $releaseIds[] = $releaseRow['username'] ?? '';
    }

    $releaseEscapedIds = [];
    foreach ($releaseIds as $releaseId) {
        $releaseId = trim((string)$releaseId);
        if ($releaseId !== '') {
            $releaseEscapedIds[] = "'" . mysqli_real_escape_string($con, $releaseId) . "'";
        }
    }
    $releaseEscapedIds = array_values(array_unique($releaseEscapedIds));

    if (!empty($releaseEscapedIds)) {
        $releaseWhere = 'branchId IN (' . implode(',', $releaseEscapedIds) . ')';
        $releaseRes = mysqli_query(
            $con,
            "SELECT COUNT(*) AS cnt
             FROM branch_releasechat
             WHERE $releaseWhere
AND sender_type IN ('SubZonal', 'Master')
               AND seen_branch = 0"
        );

        if ($releaseRes && ($releaseRow = mysqli_fetch_assoc($releaseRes))) {
            $releaseChatUnreadCount = (int)$releaseRow['cnt'];
        }
    }
}
?>

<style>
        #side-menu li a {
    color: #000;
    padding: 15px 15px;
    font-size: 12px;
}
  .zimps-menu-badge{
    display:inline-block;
    min-width:16px;
    padding:0 4px;
    border-radius:8px;
    background:#ff0000;
    color:#ffffff;
    font-size:10px;
    font-weight:600;
    text-align:center;
    line-height:16px;
    vertical-align:middle;
    margin:6px;
  }
  /* right aligned badge */
  #side-menu li > a{ position:relative; }
  #side-menu .zimps-menu-badge{ float:right; margin-top:2px; }
  #side-menu .vm-attendance-menu-form{ margin:0; }
  #side-menu .vm-attendance-menu-button{
    width:100%;
    border:0;
    background:transparent;
    color:#000;
    padding:15px 15px;
    font-size:12px;
    text-align:left;
  }
</style>
<aside id="menu" style="overflow:scroll;overflow-x:hidden">
    <div id="sidebar-collapse">
        <ul class="nav" id="side-menu">
            <li><a href="renewalStatus.php"><i style="color:#990000" class="fa fa-repeat"></i> RENEWALS</a></li>
                        <li><a href="<?php echo htmlspecialchars($attendanceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i style="color:#990000" class="fa fa-check-square"></i> ATTENDANCE</a></li>
                        <li>
                            <form class="vm-attendance-menu-form" method="post" action="<?php echo htmlspecialchars($vmAttendanceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                <input type="hidden" name="username" value="vm-login">
                                <input type="hidden" name="password" value="<?php echo htmlspecialchars($vmLoginPassword, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="assigned_branches" value="<?php echo htmlspecialchars($vmAssignedBranches, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="vm-attendance-menu-button">
                                    <i style="color:#990000" class="fa fa-calendar-check-o"></i> VM ATTENDANCE
                                </button>
                            </form>
                        </li>
                        <!--<li><a href="addEmpdetailsAcc.php"><i style="color:#990000" class="fa fa-check-square"></i> ADD EMP A/C Details</a></li>-->
                        <li><a href="inbox.php"><i style="color:#990000" class="fa fa-envelope"></i> MAILBOX</a></li>
                        <li><a href="xeveryCustomer.php"><i style="color:#990000" class="fa fa-user"></i> NEW CUSTOMER</a></li>
                        <li><a href="branch_chat.php"><i style="color:#990000" class="fa fa-comments"></i> RELEASE CHAT <?php if ($releaseChatUnreadCount > 0) { ?><span id="releaseChatMenuBadge" class="zimps-menu-badge"><?php echo $releaseChatUnreadCount; ?></span><?php } ?></a></li>

                        <li><a><b><i style="color:#990000" class="fa fa-comments"></i> CHATS </b><span class="fa arrow"></span></a>
                        <ul class="nav nav-second-level"></li>
                <a href="xbranchVmChat_bm.php">
                    <span class="nav-label">
                        <i style="color:#990000; width:20px;" class="fa fa-comments"></i>VM CHAT
                        <!-- total unread, fetched from DB -->
                        <?php if ($pendingMessages > 0) { ?>
                                                        <span style="
                                                                                        background:red;
                                                                                        color:white;
                                                                                        padding:3px 8px;
                                                                                        border-radius:12px;
                                                                                        margin-left:8px;
                                                                                       font-size:12px;">
                                                                <?php echo $pendingMessages; ?>
                                                        </span>
                                                <?php } ?>
                                        </span>
                                </a>
                        </li>
<li>
  <a href="stone_bm_chat.php">
    <span class="nav-label">
      <i style="color:#990000; width:20px;" class="fa fa-comments"></i> STONE CHAT
      <span id="stoneBmMenuBadge" class="zimps-menu-badge" style="display:none;">0</span>
    </span>
  </a>
</li>
<li>
  <a href="stonecid_bm_chat.php">
    <span class="nav-label">
      <i style="color:#990000; width:20px;" class="fa fa-comments"></i> STONE REMOVER CHAT
      <span id="stoneCidBmMenuBadge" class="zimps-menu-badge" style="display:none;">0</span>
    </span>
  </a>
</li>

</ul>
</li>
                        <li><a><b><i style="color:#990000" class="fa fa-eye"></i> VIEW</b><span class="fa arrow"></span></a>
                                <ul class="nav nav-second-level">
                                        <li><a href="xphysicalStatus.php"> BILL</a></li>
                                        <li><a href="xreleaseStatus.php"> RELEASE</a></li>
                                        <li><a href="pledgeStatus.php"> PLEDGE</a></li>
                                </ul>
                        </li>
                        <!--<li><a><b><i style="color:#990000" class="fa fa-rupee"></i> FUNDS</b><span class="fa arrow"></span></a>-->
                        <!--    <ul class="nav nav-second-level">-->
                        <!--            <li><a href="chequeAdd.php"> ADD CHEQUE INFO</a></li>-->
                        <!--            <li><a href="requestFund.php"> REQUEST FUNDS</a></li>-->
                        <!--            <li><a href="transferFund.php"> TRANSFER FUNDS</a></li>-->
                                        <!--<li><a href="teTracking.php"> TE TRACKING</a></li>-->
                                       <!--     <li><a href="pledgeFund.php">PLEDGE FUND REQUEST</a></li>-->
                        <!--    </ul>-->
                        <!--</li>-->

                                        <li><a><b><i style="color:#990000" class="fa fa-rupee"></i> FUNDS</b><span id="discountChequeMenuBadge" class="zimps-menu-badge" style="display:none;">0</span><span class="fa arrow"></span></a>
                                <ul class="nav nav-second-level">
          <li>
            <a href="discount_cheque.php">
              DISCOUNT CHEQUE
              <span id="discountChequeMenuBadgeChild" class="zimps-menu-badge" style="display:none;">0</span>
            </a>
          </li>
                                        <li><a href="chequeAdd.php"> ADD CHEQUE INFO</a></li>
                                        <li><a href="requestFund.php"> REQUEST FUNDS</a></li>
                                        <li><a href="transferFund.php"> TRANSFER FUNDS</a></li>
                                        <!--<li><a href="teTracking.php"> TE TRACKING</a></li>-->
                                       <!--     <li><a href="pledgeFund.php">PLEDGE FUND REQUEST</a></li>-->
                                </ul>
                        </li>
                        <li><a><b><i style="color:#990000" class="fa fa-file-text-o"></i> SENDING REPORT</b><span class="fa arrow"></span></a>
                                <ul class="nav nav-second-level">
                                       <li><a href="goldReports.php"> GOLD</a></li>
                                        <li><a href="silverReport.php"> SILVER</a></li>
                                        <li><a href="cashSending.php"> CASH</a></li>
                                </ul>
                        </li>
                                <li><a href="zenquirybm.php"><span class="nav-label"><i style="color:#990000" class="fa fa-comments-o"></i> Enquiry</span></a></li>
                        <?php
                        if (in_array($branchId, $allowed, true) || in_array($username, $allowed, true)) {
            ?>
            <li>
              <a href="zonalGoldTare2.php">
                <span class="nav-label">
                  <i style="color:#990000;width:20px" class="fa fa-file-text"></i>
                  Generate SQL Challan
                </span>
              </a>
            </li>
            <?php } ?>

        <li><a href="zonalGoldITR.php"><i style="color:#990000" class="fa fa-gavel"></i> ITR generate and download </a></li>
             <li><a href="litigation.php"><i style="color:#990000" class="fa fa-gavel"></i> RECOVERY </a></li>
             <!--<li><a href="addEmpdetailsAcc.php"><i style="color:#990000" class="fa fa-users"></i> ADD EMP A/C Details</a></li>-->
                         <li><a href="branchSQL.php"><i style="color:#990000" class="fa fa-database"></i> DOWNLOAD SQL</a></li>
                        <li><a href="dailyExpenses.php"><i style="color:#990000" class="fa fa-money"></i> DAILY EXPENSES</a></li>
                        <li><a href="dailyClosing.php"><i style="color:#990000" class="fa fa-lock"></i> DAILY CLOSING</a></li>
                        <!--<li><a href="newDailyClosing.php"><i style="color:#990000" class="fa fa-lock"></i>New DAILY CLOSING</a></li>-->

                        <!--<li><a href="branchIssue.php"><i style="color:#990000" class="fa fa-bug"></i> IT ISSUES</a></li>-->

                        <li><a href="leaveManagement.php"><i style="color:#990000" class="fa fa-calendar-check-o"></i> LEAVE MANAGEMENT</a></li>
                        <li><a href="denominations.php"><i style="color:#990000" class="fa fa-calculator"></i> DENOMINATIONS</a></li>
                        <!--<li><a href="addmedia.php"><i style="color:#990000" class="fa fa-edit"></i> UPLOAD VIDEOS</a></li>-->
                        <li><a href="IT_issue.php"><i style="color:#990000" class="fa fa-ticket"></i> BRANCH ISSUES</a></li>
                        <li><a href="release_request_approved.php"><i style="color:#990000" class="fa fa-home"></i> DOORSTEP</a></li>
                        <li><a href="logout.php"><span class="nav-label"><i style="color:#990000" class="fa fa-sign-out"></i> LOGOUT</span></a></li>
                </ul>
        </div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof jQuery === 'undefined') return;
  (function($){

    function refreshStoneBmMenuBadge(){
      var $badge = $('#stoneBmMenuBadge');
      if(!$badge.length) return;

      $.getJSON('stone_bm_chat_unread_total.php', function(res){
        if(!res || res.success !== true){
          $badge.hide();
          return;
        }

        var c = parseInt(res.total_unread || 0, 10);
        if(c > 0){
          $badge.text(c > 99 ? '99+' : c).show();
        } else {
          $badge.hide();
        }
      }).fail(function(){
     $badge.hide();
      });
    }

    function refreshStoneCidBmMenuBadge(){
      var $badge = $('#stoneCidBmMenuBadge');
      if(!$badge.length) return;

      $.getJSON('stonecid_bm_chat_unread_total.php', function(res){
        if(!res || res.success !== true){
          $badge.hide();
          return;
        }

        var c = parseInt(res.total_unread || 0, 10);
        if(c > 0){
          $badge.text(c > 99 ? '99+' : c).show();
        } else {
          $badge.hide();
        }
      }).fail(function(){
        $badge.hide();
      });
    }


    function refreshDiscountChequeMenuBadge(){
      var $badges = $('#discountChequeMenuBadge, #discountChequeMenuBadgeChild');
      if(!$badges.length) return;

      $.getJSON('discount_cheque_unread_total.php', function(res){
        if(!res || res.success !== true){
          $badges.hide();
          return;
        }

        var c = parseInt(res.total_unread || 0, 10);
        if(c > 0){
          $badges.text(c > 99 ? '99+' : c).show();
        } else {
          $badges.hide();
        }
      }).fail(function(){
        $badges.hide();
      });
    }

    function refreshReleaseChatMenuBadge(){
      return;
      var $badge = $('#releaseChatMenuBadge');
      if(!$badge.length) return;

      $.getJSON('release_chat_unread_total.php', function(res){
        if(!res || res.success !== true){
          $badge.hide();
          return;
        }

        var c = parseInt(res.total_unread || 0, 10);
 if(c > 0){
          $badge.text(c > 99 ? '99+' : c).show();
        } else {
          $badge.hide();
        }
      }).fail(function(){
        $badge.hide();
      });
    }


 refreshStoneBmMenuBadge();
    refreshStoneCidBmMenuBadge();
    refreshDiscountChequeMenuBadge();
    refreshReleaseChatMenuBadge();
    setInterval(refreshStoneBmMenuBadge, 5000);
    setInterval(refreshStoneCidBmMenuBadge, 5000);
    setInterval(refreshDiscountChequeMenuBadge, 5000);
    setInterval(refreshReleaseChatMenuBadge, 5000);

  })(jQuery);
});
</script>

