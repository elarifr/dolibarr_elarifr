<?php
/* Copyright (C) 2003-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/comm/propal/index.php
 *	\ingroup    propal
 *	\brief      Home page of proposal area
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT .'/comm/askpricesupplier/class/askpricesupplier.class.php';

$langs->load("askpricesupplier");
$langs->load("companies");

// Security check
$socid=GETPOST('socid','int');
if (isset($user->societe_id) && $user->societe_id  > 0)
{
	$action = '';
	$socid = $user->societe_id;
}
$result = restrictedArea($user, 'askpricesupplier');


/*
 * View
 */
$now=dol_now();
$askpricesupplierstatic=new AskPriceSupplier($db);
$companystatic=new Societe($db);
$form = new Form($db);
$formfile = new FormFile($db);
$help_url="EN:Module_Ask_Price_Supplier|FR:Module_Demande_de_prix_fournisseur";

llxHeader("",$langs->trans("AskPriceSupplierArea"),$help_url);

print_fiche_titre($langs->trans("AskPriceSupplierArea"));

print '<div class="fichecenter"><div class="fichethirdleft">';


/*
 * Search form
 */
$var=false;
print '<form method="post" action="'.DOL_URL_ROOT.'/comm/askpricesupplier/list.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<table class="noborder nohover" width="100%">';
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("SearchRequest").'</td></tr>';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("Ref").':</td><td><input type="text" class="flat" name="sref" size=18></td><td rowspan="2"><input type="submit" value="'.$langs->trans("Search").'" class="button"></td></tr>';
print '<tr '.$bc[$var].'><td class="nowrap">'.$langs->trans("Other").':</td><td><input type="text" class="flat" name="sall" size="18"></td>';
print '</tr>';
print "</table></form><br>\n";


/*
 * Statistics
 */

$sql = "SELECT count(p.rowid), p.fk_statut";
$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
$sql.= ", ".MAIN_DB_PREFIX."askpricesupplier as p";
if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= " WHERE p.fk_soc = s.rowid";
$sql.= " AND p.entity = ".$conf->entity;
if ($user->societe_id) $sql.=' AND p.fk_soc = '.$user->societe_id;
if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
$sql.= " AND p.fk_statut IN (0,1,2,3,4)";
$sql.= " GROUP BY p.fk_statut";
$resql = $db->query($sql);
if ($resql)
{
    $num = $db->num_rows($resql);
    $i = 0;

    $total=0;
    $totalinprocess=0;
    $dataseries=array();
    $vals=array();
    // -1=Canceled, 0=Draft, 1=Validated, (2=Accepted/On process not managed for customer orders), 3=Closed (Sent/Received, billed or not)
    while ($i < $num)
    {
        $row = $db->fetch_row($resql);
        if ($row)
        {
            //if ($row[1]!=-1 && ($row[1]!=3 || $row[2]!=1))
            {
                $vals[$row[1]]=$row[0];
                $totalinprocess+=$row[0];
            }
            $total+=$row[0];
        }
        $i++;
    }
    $db->free($resql);

    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("Statistics").' - '.$langs->trans("CommRequests").'</td></tr>'."\n";
    $var=true;
    $listofstatus=array(0,1,2,3,4);
    foreach ($listofstatus as $status)
    {
        $dataseries[]=array('label'=>$askpricesupplierstatic->LibStatut($status,1),'data'=>(isset($vals[$status])?(int) $vals[$status]:0));
        if (! $conf->use_javascript_ajax)
        {
            $var=!$var;
            print "<tr ".$bc[$var].">";
            print '<td>'.$askpricesupplierstatic->LibStatut($status,0).'</td>';
            print '<td align="right"><a href="list.php?statut='.$status.'">'.(isset($vals[$status])?$vals[$status]:0).'</a></td>';
            print "</tr>\n";
        }
    }
    if ($conf->use_javascript_ajax)
    {
        print '<tr '.$bc[false].'><td align="center" colspan="2">';
        $data=array('series'=>$dataseries);
        dol_print_graph('stats',300,180,$data,1,'pie',1);
        print '</td></tr>';
    }

    print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td><td align="right">'.$total.'</td></tr>';
    print "</table><br>";
}
else
{
    dol_print_error($db);
}


/*
 * Draft askprice
 */
if (! empty($conf->askpricesupplier->enabled))
{
	$sql = "SELECT c.rowid, c.ref, s.nom as socname, s.rowid as socid, s.canvas, s.client";
	$sql.= " FROM ".MAIN_DB_PREFIX."askpricesupplier as c";
	$sql.= ", ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE c.fk_soc = s.rowid";
	$sql.= " AND c.entity = ".$conf->entity;
	$sql.= " AND c.fk_statut = 0";
	if ($socid) $sql.= " AND c.fk_soc = ".$socid;
	if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;

	$resql=$db->query($sql);
	if ($resql)
	{
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<td colspan="2">'.$langs->trans("DraftRequests").'</td></tr>';
		$langs->load("askpricesupplier");
		$num = $db->num_rows($resql);
		if ($num)
		{
			$i = 0;
			$var = True;
			while ($i < $num)
			{
				$var=!$var;
				$obj = $db->fetch_object($resql);
				print "<tr ".$bc[$var].">";

				$askpricesupplierstatic->id=$obj->rowid;
				$askpricesupplierstatic->ref=$obj->ref;
				print '<td class="nowrap">'.$askpricesupplierstatic->getNomUrl(1).'</td>';

				$companystatic->id=$obj->socid;
				$companystatic->name=$obj->socname;
				$companystatic->client=$obj->client;
				$companystatic->canvas=$obj->canvas;
				print '<td>'.$companystatic->getNomUrl(1,'customer',24).'</td>';

				print '</tr>';
				$i++;
			}
		}
		print "</table><br>";
	}
}

print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';


$max=5;

/*
 * Last modified askprice
 */

$sql = "SELECT c.rowid, c.ref, c.fk_statut, s.nom as socname, s.rowid as socid, s.canvas, s.client,";
$sql.= " date_cloture as datec";
$sql.= " FROM ".MAIN_DB_PREFIX."askpricesupplier as c";
$sql.= ", ".MAIN_DB_PREFIX."societe as s";
if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= " WHERE c.fk_soc = s.rowid";
$sql.= " AND c.entity = ".$conf->entity;
//$sql.= " AND c.fk_statut > 2";
if ($socid) $sql .= " AND c.fk_soc = ".$socid;
if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
$sql.= " ORDER BY c.tms DESC";
$sql.= $db->plimit($max, 0);

$resql=$db->query($sql);
if ($resql)
{
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td colspan="4">'.$langs->trans("LastModifiedRequests",$max).'</td></tr>';

	$num = $db->num_rows($resql);
	if ($num)
	{
		$i = 0;
		$var = True;
		while ($i < $num)
		{
			$var=!$var;
			$obj = $db->fetch_object($resql);

			print "<tr ".$bc[$var].">";
			print '<td width="20%" class="nowrap">';

			$askpricesupplierstatic->id=$obj->rowid;
			$askpricesupplierstatic->ref=$obj->ref;

			print '<table class="nobordernopadding"><tr class="nocellnopadd">';
			print '<td width="96" class="nobordernopadding nowrap">';
			print $askpricesupplierstatic->getNomUrl(1);
			print '</td>';

			print '<td width="16" class="nobordernopadding nowrap">';
			print '&nbsp;';
			print '</td>';

			print '<td width="16" align="right" class="nobordernopadding">';
			$filename=dol_sanitizeFileName($obj->ref);
			$filedir=$conf->askpricesupplier->dir_output . '/' . dol_sanitizeFileName($obj->ref);
			$urlsource=$_SERVER['PHP_SELF'].'?id='.$obj->rowid;
			print $formfile->getDocumentsLink($askpricesupplierstatic->element, $filename, $filedir);
			print '</td></tr></table>';

			print '</td>';

			$companystatic->id=$obj->socid;
			$companystatic->name=$obj->socname;
			$companystatic->client=$obj->client;
			$companystatic->canvas=$obj->canvas;
			print '<td>'.$companystatic->getNomUrl(1,'customer').'</td>';

			print '<td>'.dol_print_date($db->jdate($obj->datec),'day').'</td>';
			print '<td align="right">'.$askpricesupplierstatic->LibStatut($obj->fk_statut,5).'</td>';
			print '</tr>';
			$i++;
		}
	}
	print "</table><br>";
}
else dol_print_error($db);


/*
 * Opened askprice
 */
if (! empty($conf->askpricesupplier->enabled) && $user->rights->askpricesupplier->lire)
{
	$langs->load("askpricesupplier");

	$now=dol_now();

	$sql = "SELECT s.nom as socname, s.rowid as socid, s.canvas, s.client, p.rowid as askpricesupplierid, p.total as total_ttc, p.total_ht, p.ref, p.fk_statut, p.datec as dp";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql.= ", ".MAIN_DB_PREFIX."askpricesupplier as p";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE p.fk_soc = s.rowid";
	$sql.= " AND p.entity = ".$conf->entity;
	$sql.= " AND p.fk_statut = 1";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid) $sql.= " AND s.rowid = ".$socid;
	$sql.= " ORDER BY p.rowid DESC";

	$result=$db->query($sql);
	if ($result)
	{
		$total = 0;
		$num = $db->num_rows($result);
		$i = 0;
		if ($num > 0)
		{
			$var=true;

			print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre"><td colspan="5">'.$langs->trans("RequestsOpened").' <a href="'.DOL_URL_ROOT.'/comm/askpricesupplier/list.php?viewstatut=1"><span class="badge">'.$num.'</span></a></td></tr>';

			$nbofloop=min($num, (empty($conf->global->MAIN_MAXLIST_OVERLOAD)?500:$conf->global->MAIN_MAXLIST_OVERLOAD));
			while ($i < $nbofloop)
			{
				$obj = $db->fetch_object($result);
				$var=!$var;
				print '<tr '.$bc[$var].'>';

				// Ref
				print '<td class="nowrap" width="140">';

				$askpricesupplierstatic->id=$obj->askpricesupplierid;
				$askpricesupplierstatic->ref=$obj->ref;

				print '<table class="nobordernopadding"><tr class="nocellnopadd">';
				print '<td class="nobordernopadding nowrap">';
				print $askpricesupplierstatic->getNomUrl(1);
				print '</td>';
				print '<td width="18" class="nobordernopadding nowrap">';
				if ($db->jdate($obj->dfv) < ($now - $conf->askpricesupplier->cloture->warning_delay)) print img_warning($langs->trans("Late"));
				print '</td>';
				print '<td width="16" align="center" class="nobordernopadding">';
				$filename=dol_sanitizeFileName($obj->ref);
				$filedir=$conf->askpricesupplier->dir_output . '/' . dol_sanitizeFileName($obj->ref);
				$urlsource=$_SERVER['PHP_SELF'].'?id='.$obj->askpricesupplierid;
				print $formfile->getDocumentsLink($askpricesupplierstatic->element, $filename, $filedir);
				print '</td></tr></table>';

				print "</td>";

				$companystatic->id=$obj->socid;
				$companystatic->name=$obj->socname;
				$companystatic->client=$obj->client;
				$companystatic->canvas=$obj->canvas;
				print '<td align="left">'.$companystatic->getNomUrl(1,'customer',44).'</td>'."\n";

				print '<td align="right">';
				print dol_print_date($db->jdate($obj->dp),'day').'</td>'."\n";
				print '<td align="right">'.price($obj->total_ttc).'</td>';
				print '<td align="center" width="14">'.$askpricesupplierstatic->LibStatut($obj->fk_statut,3).'</td>'."\n";
				print '</tr>'."\n";
				$i++;
				$total += $obj->total_ttc;
			}
			if ($num > $nbofloop)
			{
				print '<tr class="liste_total"><td colspan="5">'.$langs->trans("XMoreLines", ($num - $nbofloop))."</td></tr>";
			}
			else if ($total>0)
			{
				print '<tr class="liste_total"><td colspan="3">'.$langs->trans("Total")."</td><td align=\"right\">".price($total)."</td><td>&nbsp;</td></tr>";
			}
			print "</table><br>";
		}
	}
	else
	{
		dol_print_error($db);
	}
}

print '</div></div></div>';

llxFooter();

$db->close();
