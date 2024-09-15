{{ Illuminate\Mail\Markdown::parse('---') }}

Thank you,<br>
{{ config('app.name') ?? 'Devlab' }}

{{ Illuminate\Mail\Markdown::parse('[Contact Support](https://devlab.id/docs/contact)') }}
