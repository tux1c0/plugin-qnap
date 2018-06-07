<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class qnap extends eqLogic {
    /*     * *************************Attributs****************************** */
	
    /*     * ***********************Methode static*************************** */
	public static function dependancy_info() {
		$return = array();
		$return['progress_file'] = jeedom::getTmpFolder('qnap') . '/dependance';
		if (exec(system::getCmdSudo() . system::get('cmd_check') . '-E "php\-ssh2|php5\-snmp|php\-snmp" | wc -l') >= 1) {
			$return['state'] = 'ok';
		} else {
			$return['state'] = 'nok';
		}
		return $return;
	}
	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install.sh ' . jeedom::getTmpFolder('qnap') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

	public static function update($_eqLogic_id = null) {
		if ($_eqLogic_id == null) {
			$eqLogics = eqLogic::byType('qnap');
		} else {
			$eqLogics = array(eqLogic::byId($_eqLogic_id));
		}
		foreach ($eqLogics as $qnap) {
			$autorefresh = "*/15 * * * *";
			try {
				$c = new Cron\CronExpression($autorefresh, new Cron\FieldFactory);
				if ($c->isDue()) {
					try {
						$qnap->getQNAPInfo();
					} catch (Exception $e) {
						log::add('qnap', 'error', $e->getMessage());
					}
				}
			} catch (Exception $exc) {
				log::add('qnap', 'error', __('Expression cron non valide pour ', __FILE__) . $qnap->getHumanName() . ' : ' . $autorefresh);
			}
		}
	}
	
	public function getQNAPInfo() {
		// getting configuration
		$IPaddress = $this->getConfiguration('ip');
		$login = $this->getConfiguration('username');
		$pwd = $this->getConfiguration('password');
		$community = $this->getConfiguration('snmp');
		$NAS = $this->getName();
		
		// var
		$this->infos = array(
			'cpu' 		=> '',
			'cpumodel'	=> '',
			'ram' 		=> '',
			'ramtot'	=> '',
			'ramused'	=> '',
			'hdd'		=> '',
			'hddtot'	=> '',
			'hddused'	=> '',
			'os' 		=> '',
			'status'	=> ''
		);

		// commands
		$cmdCPU = "1.3.6.1.4.1.24681.1.2.1.0";
		$cmdCPUinfos = "cat /proc/cpuinfo |  grep '^model name' | head -1 | awk '{ print $4,$5,$6,$7,$9 }'";
		$cmdRAMtot = "cat /proc/meminfo |  grep '^MemTotal' | awk '{ print $2 }'";
		$cmdRAMfree = "cat /proc/meminfo |  grep '^MemFree' | awk '{ print $2 }'";
		$cmdHDD = "df -h /dev/md0 | grep '/dev/md0' | head -1 | awk '{ print $2,$3,$5 }'";
		$cmdOS = "uname -rnsm";

		// SSH connection & launch commands
		if ($this->startSSH($IPaddress, $NAS, $login, $pwd)) {
			$this->infos['cpu'] = $this->execSNMP($IPaddress, $community, $cmdCPU);
			$this->infos['cpumodel'] = $this->execSSH($cmdCPUinfos);
			
			$ramtot = $this->execSSH($cmdRAMtot);
			$ramfree = $this->execSSH($cmdRAMfree);
			$this->infos['ramused'] = round(($ramtot-$ramfree)/1024).'M';
			$this->infos['ram'] = round(100-($ramfree*100/$ramtot));
			$this->infos['ramtot'] = round($ramtot/1024).'M';
			
			$hdd_output = $this->execSSH($cmdHDD);
			$hdd_output_array = explode(" ", $hdd_output);
			$this->infos['hdd'] = str_replace('%', '', $hdd_output_array[2]);
			$this->infos['hddtot'] = $hdd_output_array[0];
			$this->infos['hddused'] = $hdd_output_array[1];
			
			$this->infos['os'] = $this->execSSH($cmdOS);	
			$this->infos['status'] = "Up";
		} else {
			$this->infos['status'] = "Down";
		}
		
		// close SSH
		$this->disconnect($NAS);
		
		$this->updateInfo();
	}
	
	// update HTML
	public function updateInfo() {
		foreach ($this->getCmd('info') as $cmd) {
			try {
				$key = $cmd->getLogicalId();
				$value = $this->infos[$key];
				$this->checkAndUpdateCmd($cmd, $value);
				log::add('qnap', 'debug', 'key '.$key. ' valeur '.$value);
			} catch (Exception $e) {
				log::add('qnap', 'error', 'Impossible de mettre à jour le champs '.$key);
			}
		}
	}
	
	// execute SNMP command
	private function execSNMP($ip, $com, $oid) {
		$cmdOutput = snmp2_walk($ip, $com, $oid);
		log::add('qnap', 'debug', 'Commande SNMP IP='.$ip.' OID='.$oid. ', communauté='.$com.' retourne ' .$cmdOutput[0]);
		$output = explode(':', $cmdOutput[0]);
		$out = trim(trim(trim($output[1]), '"'));
		log::add('qnap', 'debug', 'out ' .$out);
		return $out;
	}
	
	// execute SSH command
	private function execSSH($cmd) {
		$cmdOutput = ssh2_exec($this->SSH, $cmd);
		log::add('qnap', 'debug', 'Commande '.$cmd);
		stream_set_blocking($cmdOutput, true);
		$output = stream_get_contents($cmdOutput);
		log::add('qnap', 'debug', 'Retour Commande '.$output);
		return $output;
	}
	
	// establish SSH
	private function startSSH($ip, $name, $user, $pass) {
		// SSH connection
		if (!$this->SSH = ssh2_connect($ip, 22)) {
			log::add('qnap', 'error', 'Impossible de se connecter en SSH au NAS '.$name);
			return 0;
		}else{
			// SSH authentication
			if (!ssh2_auth_password($this->SSH, $user, $pass)){
				log::add('qnap', 'error', 'Mauvais login/password pour '.$name);
				return 0;
			}else{
				log::add('qnap', 'debug', 'Connexion OK pour '.$name);
				return 1;
			}
		}	
	}
	
	// Close SSH connection
	private function disconnect($name) {
        if (!ssh2_disconnect($this->SSH)) {
			log::add('qnap', 'error', 'Erreur de déconnexion pour '.$name);
		}
        $this->SSH = null;
    }
	
		/*     * *********************Methode d'instance************************* */

	public function postSave() {
		
		$QNAPCmd = $this->getCmd(null, 'status');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'status');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Statut', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('status');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'cpu');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'cpu');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('CPU', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('cpu');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('numeric');
			$QNAPCmd->setUnite( '%' );
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'cpumodel');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'cpumodel');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Modèle CPU', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('cpumodel');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'ram');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'ram');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('RAM', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('ram');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('numeric');
			$QNAPCmd->setUnite( '%' );
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'ramtot');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'ramtot');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Capacité RAM', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('ramtot');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'ramused');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'ramused');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Utilisation RAM', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('ramused');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'hdd');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'hdd');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('HDD', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('hdd');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('numeric');
			$QNAPCmd->setUnite( '%' );
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'hddtot');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'hddtot');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Capacité HDD', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('hddtot');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'hddused');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'hddused');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Utilisation HDD', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('hddused');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->save();
		}
		
		
		$QNAPCmd = $this->getCmd(null, 'os');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'os');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('OS', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('os');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'refresh');
		if (!is_object($QNAPCmd)) {
			log::add('qnap', 'debug', 'refresh');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Rafraîchir', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('refresh');
			$QNAPCmd->setType('action');
			$QNAPCmd->setSubType('other');
			$QNAPCmd->save();
		}

		if ($this->getIsEnable()) {
			$this->getQNAPInfo();
		}
	}
	
}

class qnapCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */


    public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		if ($this->getLogicalId() == 'refresh') {
			$eqLogic->getQNAPInfo();
		} else if ($this->type == 'action') {
			$eqLogic->cli_execCmd($this->getConfiguration('usercmd'));
		}
		return true;
	}

    /*     * **********************Getteur Setteur*************************** */
}