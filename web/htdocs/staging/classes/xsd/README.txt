Schema XSD ufficiale CBI per le distinte SEPA.

Mettere qui il file CBIPaymentRequest.00.04.01.xsd (e gli eventuali XSD che
importa, con i nomi originali). Si ottiene dal Consorzio CBI o dalla banca
(UniCredit: documentazione "XML SEPA CBI v.04.01").

Se il file c'e', SepaCbiExport::validate() controlla ogni distinta prima del
download e blocca i file non conformi. Se manca, la validazione di schema
viene saltata (restano i controlli applicativi).
