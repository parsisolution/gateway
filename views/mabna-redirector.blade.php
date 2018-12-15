<html>
<body>
<script>
    var form = document.createElement("form");
    form.setAttribute("method", "POST");
    form.setAttribute("action", "{{ $url }}");
    form.setAttribute("target", "_self");

    var hiddenField = document.createElement("input");
    hiddenField.setAttribute("name", "TerminalID");
    hiddenField.setAttribute("value", "{{ $terminalId }}");

    var hiddenField2 = document.createElement("input");
    hiddenField2.setAttribute("name", "Amount");
    hiddenField2.setAttribute("value", "{{ $transaction->getAmount()->getRiyal() }}");

    var hiddenField3 = document.createElement("input");
    hiddenField3.setAttribute("name", "callbackURL");
    hiddenField3.setAttribute("value", "{!! $callback !!}");

    var hiddenField4 = document.createElement("input");
    hiddenField4.setAttribute("name", "InvoiceID");
    hiddenField4.setAttribute("value", "{{ $transaction->getId() }}");


    form.appendChild(hiddenField);
    form.appendChild(hiddenField2);
    form.appendChild(hiddenField3);
    form.appendChild(hiddenField4);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
</script>
</body>
</html>
