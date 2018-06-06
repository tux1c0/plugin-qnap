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
	//private $SSH;
	
    /*     * ***********************Methode static*************************** */
	public static function dependancy_info() {
		$return = array();
		$return['progress_file'] = jeedom::getTmpFolder('qnap') . '/dependance';
		if (exec(system::getCmdSudo() . system::get('cmd_check') . '-E "php-ssh2" | wc -l') >= 1) {
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
					if ($qnap->getCache('askToEqLogic', 0) > 3) {
						log::add('qnap', 'error', __('Trop d\'interrogation sans retour, désactivation des demandes : ', __FILE__) . $qnap->getHumanName());
						$qnap->setStatus('timeout', 1);
						continue;
					}
					try {
						$prevAskToEqLogic = $qnap->getCache('askToEqLogic', 0);
						$qnap->setCache('askToEqLogic', $prevAskToEqLogic + 1);
						$qnap->getQNAPInfo();
						if ($qnap->getCache('askToEqLogic', 0) == ($prevAskToEqLogic + 1)) {
							$qnap->setCache('askToEqLogic', 0);
						}
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
		$NAS = $this->getName();
		
		// var
		$this->infos = array(
			'cpu' 		=> '',
			'ram' 		=> '',
			'hdd'		=> '',
			'os' 		=> '',
			'status'	=> ''
		);

		// commands
		$cmdCPU = "lscpu | grep '^CPU(s)' | awk '{ print $NF }'";
		$cmdRAM = "";
		$cmdHDD = "";
		$cmdOS = "uname -rnsm";

		// SSH connection & launch commands
		if ($this->startSSH($IPaddress, $NAS, $login, $pwd)) {
			//$this->infos['cpu'] = $this->execSSH($cmdCPU);
			//$this->infos['ram'] = $this->execSSH($cmdRAM);
			//$this->infos['hdd'] = $this->execSSH($cmdHDD);
			$this->infos['os'] = $this->execSSH($cmdOS);	
			$this->infos['status'] = "Up";
		} else {
			$this->infos['status'] = "Down";
		}
		
		// close SSH
		$this->disconnect($NAS);
		
		$this->updateInfo();
    
	}
	
	public function updateInfo() {
		foreach ($this->getCmd('info') as $cmd) {
			try {
				$key = $cmd->getLogicalId();
				$value = $this->infos[$key];
				$this->checkAndUpdateCmd($cmd, $value);
			} catch (Exception $e) {
				log::add('qnap', 'error', 'Impossible de mettre à jour le champs '.$key);
			}
		}
	}
	
	// execute SNMP command
	private function execSNMP($ip, $com, $oid) {
		$cmdOutput = snmp2_walk($ip, $com, $oid);
		log::add('qnap', 'debug', 'Commande SNMP '.$oid);
		return $cmdOutput;
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
		
		$this->setCache('askToEqLogic', 0);
	}
	
}

class qnapCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */


    public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		if ($this->getLogicalId() == 'refresh') {
			$eqLogic->setCache('askToEqLogic', 0);
			$eqLogic->getQNAPInfo();
		} else if ($this->type == 'action') {
			$eqLogic->cli_execCmd($this->getConfiguration('usercmd'));
		}
		return true;
	}

    /*     * **********************Getteur Setteur*************************** */
}