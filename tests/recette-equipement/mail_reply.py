#!/usr/bin/env python3
"""Create explicitly fictional Outlook responses; never sends mail."""
import argparse
from pathlib import Path
from datetime import datetime,timezone
from aspose.email_foss.msg import MapiMessage,PropertyId
p=argparse.ArgumentParser();p.add_argument('reference');p.add_argument('pdf');p.add_argument('output');p.add_argument('--invalid',action='store_true');a=p.parse_args()
ref='REFERENCE-ERRONEE-RECETTE' if a.invalid else a.reference
subject='[FICTIF RECETTE] RE: Validation CDC ATLAS-12 - '+ref
body='CDC Accepté\r\n\r\nCommentaire :\r\nAccord fictif généré pour la recette logicielle. Les douze fonctions et trente réponses techniques sont acceptées dans la simulation uniquement. Aucune personne réelle ne donne son accord par ce message.\r\n\r\nRéférence de validation : '+ref+'\r\n'
m=MapiMessage.create(subject,body,unicode_strings=True)
m.set_property(PropertyId.SENDER_NAME,'Validateur fictif - recette automatique')
m.set_property(PropertyId.SENDER_EMAIL_ADDRESS,'validation-recette@example.invalid')
m.set_property(PropertyId.CLIENT_SUBMIT_TIME,datetime.now(timezone.utc))
m.add_recipient('recette-oddworks@example.invalid')
m.add_attachment('CDC_ATLAS12_FICTIF.pdf',Path(a.pdf).read_bytes(),mime_type='application/pdf')
m.set_property(PropertyId.MESSAGE_FLAGS,0x11)
m.save(a.output)
with MapiMessage.from_file(a.output,strict=True) as saved:
 assert saved.subject==subject and saved.body==body and len(saved.attachments)==1
print('Réponse fictive MSG sérialisée et relue')
