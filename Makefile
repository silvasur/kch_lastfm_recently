PHP = php

kch_lastfm_recently.txt: kch_lastfm_recently.php
	$(PHP) kch_lastfm_recently.php > kch_lastfm_recently.txt
