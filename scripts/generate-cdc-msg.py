#!/usr/bin/env python3
"""Generate one disposable Outlook draft MSG with the archived CDC attached."""
import html
import json
import sys
from pathlib import Path

from aspose.email_foss.msg import MapiMessage, PropertyId, RECIPIENT_TYPE_TO


def main() -> int:
    request = json.load(sys.stdin)
    recipient = str(request["recipient"])
    subject = str(request["subject"])
    project = str(request["project"])
    reference = str(request["reference"])
    pdf_path = Path(request["pdf_path"])
    pdf_name = Path(request["pdf_name"]).name
    output_path = Path(request["output_path"])

    if not pdf_path.is_file() or pdf_path.stat().st_size == 0:
        raise ValueError("Archived CDC PDF is missing or empty")

    plain_body = (
        f"Bonjour,\r\n\r\n"
        f"Merci de valider le cahier des charges du projet « {project} ».\r\n\r\n"
        "Répondez en indiquant exactement l’une des décisions suivantes :\r\n"
        "CDC Accepté\r\nCDC Refusé\r\n\r\n"
        "Commentaire :\r\n[Votre commentaire]\r\n\r\n"
        f"Référence de validation : {reference}\r\n"
        "Merci de conserver cette référence dans l’objet ou le corps de votre réponse.\r\n\r\n"
        "Cordialement"
    )
    safe_project = html.escape(project, quote=True)
    safe_reference = html.escape(reference, quote=True)
    html_body = (
        '<html><body style="margin:0;padding:0;font-family:Arial,sans-serif">'
        "<p>Bonjour,</p>"
        f"<p>Merci de valider le cahier des charges du projet « {safe_project} ».</p>"
        "<p>Répondez en indiquant exactement l’une des décisions suivantes :<br>"
        "<strong>CDC Accepté</strong><br><strong>CDC Refusé</strong></p>"
        "<p>Commentaire :<br>[Votre commentaire]</p>"
        f"<p>Référence de validation : <strong>{safe_reference}</strong><br>"
        "Merci de conserver cette référence dans l’objet ou le corps de votre réponse.</p>"
        "<p>Cordialement</p></body></html>"
    )

    message = MapiMessage.create(subject, plain_body, unicode_strings=True)
    message.body_html = html_body
    message.add_recipient(recipient, display_name=None, recipient_type=RECIPIENT_TYPE_TO)
    message.add_attachment(pdf_name, pdf_path.read_bytes(), mime_type="application/pdf")
    # MSGFLAG_UNSENT | MSGFLAG_HASATTACH: open as an editable, unsent draft.
    message.set_property(PropertyId.MESSAGE_FLAGS, 0x00000018)
    message.save(output_path)

    # Verify the serialized MSG fields before PHP returns it to the browser.
    with MapiMessage.from_file(output_path, strict=True) as saved:
        if saved.subject != subject or not saved.body_html or len(saved.recipients) != 1:
            raise ValueError("Generated MSG did not preserve its draft fields")
        if saved.recipients[0].email_address.lower() != recipient.lower():
            raise ValueError("Generated MSG recipient did not round-trip")
        if len(saved.attachments) != 1 or saved.attachments[0].filename != pdf_name:
            raise ValueError("Generated MSG PDF attachment did not round-trip")
        flags = int(saved.get_property_value(PropertyId.MESSAGE_FLAGS) or 0)
        if flags & 0x00000008 == 0:
            raise ValueError("Generated MSG is not marked as an unsent draft")

    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:  # concise error goes to the PHP error log
        print(f"{type(exc).__name__}: {exc}", file=sys.stderr)
        raise SystemExit(1)
