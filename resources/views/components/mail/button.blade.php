@props(['url', 'color' => '#1d4ed8', 'width' => 260, 'flush' => false])
{{-- bulletproof button: real padding for every client, a VML shape for desktop Outlook --}}
<table role="presentation" cellpadding="0" cellspacing="0" class="btn-table" style="margin: {{ $flush ? '22px 0 0' : '28px 0' }};">
    <tr>
        <td align="center" class="btn-cell dm-btn" bgcolor="{{ $color }}" style="background-color: {{ $color }}; border-radius: 10px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height: 50px; v-text-anchor: middle; width: {{ $width }}px;" arcsize="20%" stroke="f" fillcolor="{{ $color }}">
                <w:anchorlock/>
                <center style="color: #ffffff; font-family: Arial, sans-serif; font-size: 16px; font-weight: bold;">{{ strip_tags($slot) }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 15px 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 20px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; mso-hide: all;">{{ $slot }}</a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
