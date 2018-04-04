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

class QNAP extends eqLogic {
    /*     * *************************Attributs****************************** */
	private $SSH;


    /*     * ***********************Methode static*************************** */
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'QNAP_dependancy';
		$cmd = "php -m | grep ssh2 | wc -l";
		exec($cmd, $output, $return_var);
		if ($output[0] != 0) {
		  $return['state'] = 'ok';
		} else {
		  $return['state'] = 'nok';
		}
		return $return;
	}

	public static function dependancy_install() {
		log::remove('QNAP_update');
		$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../resources/install.sh';
		$cmd .= ' >> ' . log::getPathToLog('QNAP_dependancy') . ' 2>&1 &';
		exec($cmd);
	}
    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */



    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    public function postSave() {
		log::add('QNAP', 'debug', 'postSave');
        $QNAPCmd = $this->getCmd(null, 'cpu');
		if (!is_object($QNAPCmd)) {
			$QNAPCmd = new QNAPCmd();
			$QNAPCmd->setName(__('CPU', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('cpu');
			$QNAPCmd->setType('info');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->save();
		}
		
		foreach (eqLogic::byType('QNAP') as $QNAP) {
			$QNAP->getInformations();
		}
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
        
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }
	
	public function getInformations() {
		// getting configuration
		$IPaddress = $this->getConfiguration('IPaddress');
		$login = $this->getConfiguration('SSHlogin');
		$pwd = $this->getConfiguration('SSHpwd');
		$NAS = $this->getName();
		
		// var
		$infos = array(
			'cpu' 	=> '',
			'ram' 	=> '',
			'os' 	=> ''
		);

		// commands
		$cmdCPU = "lscpu | grep '^CPU(s)' | awk '{ print $NF }'";
		$cmdRAM = "";
		$cmdOS = "";

		// SSH connection & launch commands
		if ($this->startSSH($IPaddress, $NAS, $login, $pwd)) {
			$infos['cpu'] = $this->execSSH($cmdCPU);
			
		}
		
		// close SSH
		$this->disconnect();
		
		$this->updateInfo('cpu', $infos['cpu']);
    
	}

	// execute SSH command
	private function execSSH($cmd) {
		$cmdOutput = ssh2_exec($this->SSH, $cmd);
		stream_set_blocking($cmdOutput, true);
		$output = stream_get_contents($cmdOutput);
		
		$return $output;
	}
	
	// establish SSH
	private function startSSH($ip, $name, $user, $pass) {
		// SSH connection
		if (!$this->SSH = ssh2_connect($ip, 22)) {
			log::add('QNAP', 'error', 'Impossible de se connecter en SSH au NAS '.$name);
			return 1;
		}else{
			// SSH authentication
			if (!ssh2_auth_password($this->SSH, $user, $pass)){
				log::add('QNAP', 'error', 'Mauvais login/password pour '.$name);
				return 1;
			}else{
				return 0;
			}
		}	
	}
	
	// Close SSH connection
	private function disconnect() {
        $this->exec('echo "EXITING" && exit;');
        $this->SSH = null;
    } 
	
	// display
	private function updateInfo($objHtml, $info) {
		$obj = $this->getCmd(null, $objHtml);
		if(is_object($obj)){
			$obj->event($info);
		}
	}
    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class QNAPCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = null) {
        
    }

    /*     * **********************Getteur Setteur*************************** */
}