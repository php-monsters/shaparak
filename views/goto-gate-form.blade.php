<form id="goto_gate_form" class="form-horizontal goto-gate-form goto-{{$gateway}}-form" method="{{ $method }}" action="{{ $action }}">
    @foreach($parameters as $field => $value)
        @if(!is_null($value))
            <input type="hidden" name="{{ $field }}" value="{{ $value }}" />
        @endif
    @endforeach

  <div class="control-group">
      <div class="controls">
          <button id="goto_gate_button" type="submit" class="btn btn-success">{{ $buttonLabel }}</button>
      </div>
  </div>
</form>

@if($autoSubmit === true)
<script type="text/javascript">
    var f = document.getElementById ('goto_gate_form');
    f.submit ();
</script>
@endif


