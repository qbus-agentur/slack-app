sync:
	rsync --verbose -e 'ssh -p222' --exclude token --exclude logs --delete -az ./ qbusio@qbus.de:public_html/slack/
