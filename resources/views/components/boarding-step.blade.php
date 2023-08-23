<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
    <div class="box-border col-span-2 min-w-[24rem] min-h-[21rem]">
        <h1 class="text-5xl font-bold">{{$title}}</h1>
        <div class="py-6 ">
            @isset($question)
            <p class="text-base">
                {{$question}}
            </p>
            @endisset
        </div>
        @if($actions)
        <div class="flex flex-col flex-wrap gap-4 md:flex-row">
            {{$actions}}
        </div>
        @endif
    </div>
    @if($explanation)
    <div class="col-span-1">
        <h1 class="pb-8 font-bold">Explanation</h1>
        <div class="space-y-4">
            {{$explanation}}
        </div>
    </div>
    @endif
</div>
