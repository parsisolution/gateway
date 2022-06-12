<html>
    <body>
        <script>
        	var form = document.createElement("form");
        	form.setAttribute("method", "POST");
        	form.setAttribute("action", "{{$URL}}");
        	form.setAttribute("target", "_self");

            var params = {
                @foreach($Data as $key => $value)
                    {{$key}}: "{{$value}}",
                @endforeach
            };

            for(var key in params){

                var hiddenField = document.createElement("input");
                hiddenField.setAttribute("name", key);
                hiddenField.setAttribute("value", params[key]);

                form.appendChild(hiddenField);
            }


        	document.body.appendChild(form);
        	form.submit();
        	document.body.removeChild(form);
        </script>
    </body>
</html>