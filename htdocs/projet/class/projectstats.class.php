<?php
/* Lead
 * Copyright (C) 2014-2015 Florian HENRY <florian.henry@open-concept.pro>
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
include_once DOL_DOCUMENT_ROOT . '/core/class/stats.class.php';
include_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';


/**
 * Class to manage statistics on projects
 */
class ProjectStats extends Stats
{
	protected $db;
	private $project;
	public $userid;
	public $socid;
	public $year;
	
	function __construct($db) 
	{
		global $conf, $user;

		$this->db = $db;

		require_once 'project.class.php';
		$this->project = new Project($this->db);
	}


	/**
	 * Return all leads grouped by status
	 *
	 * @param int $limit Limit results
	 * @return array|int
	 * @throws Exception
	 */
	function getAllProjectByStatus($limit = 5)
	{
		global $conf, $user, $langs;

		$datay = array ();

		$sql = "SELECT";
		$sql .= " count(DISTINCT t.rowid), t.fk_opp_status";
		$sql .= " FROM " . MAIN_DB_PREFIX . "projet as t";
		$sql .= $this->buildWhere();
		$sql .= " AND t.fk_statut = 1";
		$sql .= " GROUP BY t.fk_opp_status";

		$result = array ();
		$res = array ();

		dol_syslog(get_class($this) . '::' . __METHOD__ . "", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			$other = 0;
			while ( $i < $num ) {
				$row = $this->db->fetch_row($resql);
				if ($i < $limit || $num == $limit)
					$result[$i] = array (
							$this->projet->status[$row[1]] . '(' . $row[0] . ')',
							$row[0]
					);
				else
					$other += $row[1];
				$i ++;
			}
			if ($num > $limit)
				$result[$i] = array (
						$langs->transnoentitiesnoconv("Other"),
						$other
				);
			$this->db->free($resql);
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . '::' . __METHOD__ . ' ' . $this->error, LOG_ERR);
			return - 1;
		}

		return $result;
	}

	/**
	 * Return count, and sum of products
	 *
	 * @return array of values
	 */
	function getAllByYear()
	{
		global $conf, $user, $langs;

		$datay = array ();

		$sql = "SELECT date_format(t.datec,'%Y') as year, COUNT(t.rowid) as nb, SUM(t.opp_amount) as total, AVG(t.opp_amount) as avg";
		$sql .= " FROM " . MAIN_DB_PREFIX . "projet as t";
		if (! $user->rights->societe->client->voir && ! $user->societe_id)
			$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON sc.fk_soc=t.fk_soc AND sc.fk_user=" . $user->id;
		$sql .= $this->buildWhere();
		$sql .= " GROUP BY year";
		$sql .= $this->db->order('year', 'DESC');

		return $this->_getAllByYear($sql);
	}
	
	
	/**
	 * Build the where part
	 * 
	 * @return string
	 */
	public function buildWhere() 
	{
		$sqlwhere_str = '';
		$sqlwhere = array();

		$sqlwhere[] = ' t.entity IN (' . getEntity('project') . ')';

		if (! empty($this->userid))
			$sqlwhere[] = ' t.fk_user_resp=' . $this->userid;
		if (! empty($this->socid))
			$sqlwhere[] = ' t.fk_soc=' . $this->socid;
		if (! empty($this->year) && empty($this->yearmonth))
			$sqlwhere[] = " date_format(t.datec,'%Y')='" . $this->year . "'";
		if (! empty($this->yearmonth))
			$sqlwhere[] = " t.datec BETWEEN '" . $this->db->idate(dol_get_first_day($this->yearmonth)) . "' AND '" . $this->db->idate(dol_get_last_day($this->yearmonth)) . "'";

		if (! empty($this->status))
			$sqlwhere[] = " t.fk_opp_status IN (" . $this->status . ")";

		if (count($sqlwhere) > 0) {
			$sqlwhere_str = ' WHERE ' . implode(' AND ', $sqlwhere);
		}

		return $sqlwhere_str;
	}

	/**
	 * Return Project number by month for a year
	 *
	 * @param int $year scan
	 * @return array of values
	 */
	function getNbByMonth($year) 
	{
		global $user;

		$this->yearmonth = $year;

		$sql = "SELECT date_format(t.datec,'%m') as dm, COUNT(*) as nb";
		$sql .= " FROM " . MAIN_DB_PREFIX . "projet as t";
		if (! $user->rights->societe->client->voir && ! $user->societe_id)
			$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON sc.fk_soc=t.fk_soc AND sc.fk_user=" . $user->id;
		$sql .= $this->buildWhere();
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		$this->yearmonth=0;

		$res = $this->_getNbByMonth($year, $sql);
		// var_dump($res);print '<br>';
		return $res;
	}

	/**
	 * Return the Project amount by month for a year
	 *
	 * @param int $year scan
	 * @return array with amount by month
	 */
	function getAmountByMonth($year) 
	{
		global $user;

		$this->yearmonth = $year;

		$sql = "SELECT date_format(t.datec,'%m') as dm, SUM(t.opp_amount)";
		$sql .= " FROM " . MAIN_DB_PREFIX . "projet as t";
		if (! $user->rights->societe->client->voir && ! $user->societe_id)
			$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON sc.fk_soc=t.fk_soc AND sc.fk_user=" . $user->id;
		$sql .= $this->buildWhere();
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');
		$this->yearmonth=0;

		$res = $this->_getAmountByMonth($year, $sql);
		// var_dump($res);print '<br>';
		return $res;
	}


	/**
	 * Return amount of elements by month for several years
	 *
	 * @param	int		$endyear		Start year
	 * @param	int		$startyear		End year
	 * @param	int		$cachedelay		Delay we accept for cache file (0=No read, no save of cache, -1=No read but save)
	 * @return 	array					Array of values
	 */
	function getWeightedAmountByMonthWithPrevYear($endyear,$startyear,$cachedelay=0)
	{
		global $conf,$user,$langs;

        if ($startyear > $endyear) return -1;

        $datay=array();

        // Search into cache
        if (! empty($cachedelay))
        {
        	include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
        	include_once DOL_DOCUMENT_ROOT.'/core/lib/json.lib.php';
        }

        $newpathofdestfile=$conf->user->dir_temp.'/'.get_class($this).'_'.__FUNCTION__.'_'.(empty($this->cachefilesuffix)?'':$this->cachefilesuffix.'_').$langs->defaultlang.'_user'.$user->id.'.cache';
        $newmask='0644';

        $nowgmt = dol_now();

        $foundintocache=0;
        if ($cachedelay > 0)
        {
        	$filedate=dol_filemtime($newpathofdestfile);
        	if ($filedate >= ($nowgmt - $cachedelay))
        	{
        		$foundintocache=1;

        		$this->_lastfetchdate[get_class($this).'_'.__FUNCTION__]=$filedate;
        	}
        	else
        	{
        		dol_syslog(get_class($this).'::'.__FUNCTION__." cache file ".$newpathofdestfile." is not found or older than now - cachedelay (".$nowgmt." - ".$cachedelay.") so we can't use it.");
        	}
        }

        // Load file into $data
        if ($foundintocache)    // Cache file found and is not too old
        {
        	dol_syslog(get_class($this).'::'.__FUNCTION__." read data from cache file ".$newpathofdestfile." ".$filedate.".");
        	$data = json_decode(file_get_contents($newpathofdestfile), true);
        }
        else
		{
			$year=$startyear;
			while($year <= $endyear)
			{
				$datay[$year] = $this->getWeightedAmountByMonth($year);
				$year++;
			}

			$data = array();
			// $data = array('xval'=>array(0=>xlabel,1=>yval1,2=>yval2...),...)
			for ($i = 0 ; $i < 12 ; $i++)
			{
				$data[$i][]=$datay[$endyear][$i][0];	// set label
				$year=$startyear;
				while($year <= $endyear)
				{
					$data[$i][]=$datay[$year][$i][1];	// set yval for x=i
					$year++;
				}
			}
		}

		// Save cache file
		if (empty($foundintocache) && ($cachedelay > 0 || $cachedelay == -1))
		{
			dol_syslog(get_class($this).'::'.__FUNCTION__." save cache file ".$newpathofdestfile." onto disk.");
			if (! dol_is_dir($conf->user->dir_temp)) dol_mkdir($conf->user->dir_temp);
			$fp = fopen($newpathofdestfile, 'w');
			if ($fp)
			{
				fwrite($fp, json_encode($data));
				fclose($fp);
				if (! empty($conf->global->MAIN_UMASK)) $newmask=$conf->global->MAIN_UMASK;
				@chmod($newpathofdestfile, octdec($newmask));
			}
			else dol_syslog("Failed to write cache file", LOG_ERR);
			$this->_lastfetchdate[get_class($this).'_'.__FUNCTION__]=$nowgmt;
		}

		return $data;
	}


	/**
	 * Return the Project weighted opp amount by month for a year
	 *
	 * @param int $year scan
	 * @return array with amount by month
	 */
	function getWeightedAmountByMonth($year) 
	{
		global $user;

		$this->yearmonth = $year;

		$sql = "SELECT date_format(t.datec,'%m') as dm, SUM(t.opp_amount * ".$this->db->ifsql('cls.percent IS NULL', '0', 'cls.percent')." / 100)";
		$sql .= " FROM " . MAIN_DB_PREFIX . "projet as t LEFT JOIN ".MAIN_DB_PREFIX.'c_lead_status as cls ON t.fk_opp_status = cls.rowid';
		if (! $user->rights->societe->client->voir && ! $user->societe_id)
			$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON sc.fk_soc=t.fk_soc AND sc.fk_user=" . $user->id;
		$sql .= $this->buildWhere();
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');
		$this->yearmonth=0;

		$res = $this->_getAmountByMonth($year, $sql);
		// var_dump($res);print '<br>';
		return $res;
	}

	/**
	 * Return amount of elements by month for several years
	 *
	 * @param int $endyear		End year
	 * @param int $startyear	Start year
	 * @param int $cachedelay accept for cache file (0=No read, no save of cache, -1=No read but save)
	 * @return array of values
	 */
	function getTransformRateByMonthWithPrevYear($endyear, $startyear, $cachedelay = 0)
	{
		global $conf, $user, $langs;

		if ($startyear > $endyear) return - 1;

		$datay = array();

		// Search into cache
		if (! empty($cachedelay))
		{
			include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
			include_once DOL_DOCUMENT_ROOT . '/core/lib/json.lib.php';
		}

		$newpathofdestfile = $conf->user->dir_temp . '/' . get_class($this) . '_' . __FUNCTION__ . '_' . (empty($this->cachefilesuffix) ? '' : $this->cachefilesuffix . '_') . $langs->defaultlang . '_user' . $user->id . '.cache';
		$newmask = '0644';

		$nowgmt = dol_now();

		$foundintocache = 0;
		if ($cachedelay > 0) {
			$filedate = dol_filemtime($newpathofdestfile);
			if ($filedate >= ($nowgmt - $cachedelay)) {
				$foundintocache = 1;

				$this->_lastfetchdate[get_class($this) . '_' . __FUNCTION__] = $filedate;
			} else {
				dol_syslog(get_class($this) . '::' . __FUNCTION__ . " cache file " . $newpathofdestfile . " is not found or older than now - cachedelay (" . $nowgmt . " - " . $cachedelay . ") so we can't use it.");
			}
		}

		// Load file into $data
		if ($foundintocache) // Cache file found and is not too old
		{
			dol_syslog(get_class($this) . '::' . __FUNCTION__ . " read data from cache file " . $newpathofdestfile . " " . $filedate . ".");
			$data = dol_json_decode(file_get_contents($newpathofdestfile), true);
		} else {
			$year = $startyear;
			while ( $year <= $endyear ) {
				$datay[$year] = $this->getTransformRateByMonth($year);
				$year ++;
			}

			$data = array ();
			// $data = array('xval'=>array(0=>xlabel,1=>yval1,2=>yval2...),...)
			for($i = 0; $i < 12; $i ++) {
				$data[$i][] = $datay[$endyear][$i][0]; // set label
				$year = $startyear;
				while ( $year <= $endyear ) {
					$data[$i][] = $datay[$year][$i][1]; // set yval for x=i
					$year ++;
				}
			}
		}

		// Save cache file
		if (empty($foundintocache) && ($cachedelay > 0 || $cachedelay == - 1)) {
			dol_syslog(get_class($this) . '::' . __FUNCTION__ . " save cache file " . $newpathofdestfile . " onto disk.");
			if (! dol_is_dir($conf->user->dir_temp))
				dol_mkdir($conf->user->dir_temp);
			$fp = fopen($newpathofdestfile, 'w');
			fwrite($fp, dol_json_encode($data));
			fclose($fp);
			if (! empty($conf->global->MAIN_UMASK))
				$newmask = $conf->global->MAIN_UMASK;
			@chmod($newpathofdestfile, octdec($newmask));

			$this->_lastfetchdate[get_class($this) . '_' . __FUNCTION__] = $nowgmt;
		}

		return $data;
	}

	/**
	 * Return the Project transformation rate by month for a year
	 *
	 * @param int $year scan
	 * @return array with amount by month
	 */
	function getTransformRateByMonth($year) 
	{
		global $user;

		$this->yearmonth = $year;

		$sql = "SELECT date_format(t.datec,'%m') as dm, count(t.opp_amount)";
		$sql .= " FROM " . MAIN_DB_PREFIX . "projet as t";
		if (! $user->rights->societe->client->voir && ! $user->societe_id)
			$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON sc.fk_soc=t.fk_soc AND sc.fk_user=" . $user->id;
		$sql .= $this->buildWhere();
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		$res_total = $this->_getNbByMonth($year, $sql);

		$this->status=6;

		$sql = "SELECT date_format(t.datec,'%m') as dm, count(t.opp_amount)";
		$sql .= " FROM " . MAIN_DB_PREFIX . "projet as t";
		if (! $user->rights->societe->client->voir && ! $user->societe_id)
			$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON sc.fk_soc=t.fk_soc AND sc.fk_user=" . $user->id;
		$sql .= $this->buildWhere();
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		$this->status=0;
		$this->yearmonth=0;

		$res_only_wined = $this->_getNbByMonth($year, $sql);

		$res=array();

		foreach($res_total as $key=>$total_row) {
			//var_dump($total_row);
			if (!empty($total_row[1])) {
				$res[$key]=array($total_row[0],(100*$res_only_wined[$key][1])/$total_row[1]);
			} else {
				$res[$key]=array($total_row[0],0);
			}

		}
		// var_dump($res);print '<br>';
		return $res;
	}
}